<?php
declare(strict_types=1);
require_once __DIR__ . '/PairingSupport.php';

class PhilipsTV extends IPSModuleStrict
{
    use PhilipsTVPairingSupport;
    private const KEYS = ['Home', 'Back', 'Find', 'Options', 'Source', 'Info', 'WatchTV',
        'CursorUp', 'CursorDown', 'CursorLeft', 'CursorRight', 'Confirm',
        'PlayPause', 'Play', 'Pause', 'Stop', 'Rewind', 'FastForward',
        'ChannelStepUp', 'ChannelStepDown', 'VolumeUp', 'VolumeDown', 'Mute',
        'RedColour', 'GreenColour', 'YellowColour', 'BlueColour',
        'Digit0', 'Digit1', 'Digit2', 'Digit3', 'Digit4', 'Digit5', 'Digit6', 'Digit7', 'Digit8', 'Digit9'];

    public function Create(): void
    {
        parent::Create();
        $this->CreatePairing();
        $this->RegisterPropertyBoolean('Enabled', false);
        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('Port', 1926);
        $this->RegisterPropertyInteger('PollInterval', 15);
        $this->RegisterPropertyInteger('StandbyInterval', 0);
        $this->RegisterPropertyInteger('WakeSignal', 0);
        $this->RegisterPropertyBoolean('FollowSources', false);
        $this->RegisterPropertyInteger('ShieldSignal', 0);
        $this->RegisterPropertyInteger('AVRSignal', 0);
        $this->RegisterPropertyBoolean('SignalPowerOn', false);
        $this->RegisterPropertyBoolean('SignalPowerOff', false);
        $this->RegisterPropertyInteger('Timeout', 5000);
        $this->RegisterPropertyBoolean('VerifyTLS', false);
        $this->RegisterAttributeString('Snapshot', '{}');
        $this->RegisterAttributeString('Unsupported', '[]');
        $this->RegisterAttributeString('Generation', '');
        $this->RegisterAttributeString('ConfigurationHash', '');
        $this->RegisterAttributeInteger('WakeSubscription', 0);
        $this->RegisterAttributeString('SourceSubscriptions', '[]');
        $this->RegisterAttributeString('SourcesState', 'unknown');
        $this->RegisterAttributeBoolean('ManualRefreshPending', false);
        $this->RegisterAttributeString('SignalState', 'unknown');
        $this->RegisterAttributeInteger('SignalMessageCounter', -1);
        $this->RegisterVariableBoolean('Online', 'TV erreichbar', '~Switch', 10);
        $this->RegisterVariableBoolean('Power', 'TV einschalten', '~Switch', 20);
        $this->RegisterVariableString('PowerState', 'Bestätigter Betriebszustand', '', 30);
        $this->RegisterVariableInteger('Volume', 'Lautstärke (%)', '~Intensity.100', 40);
        $this->RegisterVariableBoolean('Mute', 'Stumm', '~Switch', 50);
        $this->RegisterVariableString('Activity', 'Aktive Anwendung / Eingang', '', 60);
        $this->RegisterVariableBoolean('Ambilight', 'Ambilight', '~Switch', 70);
        $this->RegisterVariableString('AmbilightMode', 'Ambilight-Modus', '', 80);
        $this->RegisterVariableInteger('Updated', 'Letzte Statusabfrage', '~UnixTimestamp', 90);
        $this->RegisterVariableString('LastError', 'Diagnose', '', 100);
        $this->RegisterVariableString('State', 'TV-Status für Haussteuerung', '', 110);
        $this->RegisterVariableString('TransportInfo', 'Letzte HTTPS-Anfrage', '', 120);
        $this->RegisterVariableString('LastCommand', 'Letzter Schaltversuch', '', 130);
        $this->RegisterTimer('Poll', 0, 'SVPTV_UpdateStatus($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('Poll', 0);
        $this->WriteAttributeBoolean('ManualRefreshPending', false);
        $old = $this->ReadAttributeInteger('WakeSubscription');
        if ($old > 0) { $this->UnregisterMessage($old, VM_UPDATE); }
        foreach (json_decode($this->ReadAttributeString('SourceSubscriptions'), true) ?: [] as $id) {
            if ($id > 0) { $this->UnregisterMessage((int)$id, VM_UPDATE); }
        }
        $following = $this->ReadPropertyBoolean('FollowSources');
        $wake = $this->ReadPropertyInteger('WakeSignal');
        if (!$following && $wake > 0 && IPS_VariableExists($wake)) { $this->RegisterMessage($wake, VM_UPDATE); }
        $sourceIDs = $following ? array_values(array_unique(array_filter([
            $this->ReadPropertyInteger('ShieldSignal'), $this->ReadPropertyInteger('AVRSignal')],
            fn(int $id): bool => $id > 0 && IPS_VariableExists($id)))) : [];
        foreach ($sourceIDs as $id) { $this->RegisterMessage($id, VM_UPDATE); }
        $this->WriteAttributeString('SourceSubscriptions', json_encode($sourceIDs, JSON_THROW_ON_ERROR));
        $this->WriteAttributeString('SourcesState', $following ? $this->CombinedSourcesState() : 'unknown');
        $this->WriteAttributeInteger('WakeSubscription', $wake);
        // Establish a baseline only. Enabling an option must never switch the TV.
        $signalState = 'unknown';
        if ($wake > 0 && IPS_VariableExists($wake)) {
            try { $signalState = $this->ReadSignalState($wake); }
            catch (Throwable $e) { /* A missing source cannot produce a power transition. */ }
        }
        $this->WriteAttributeString('SignalState', $signalState);
        $this->WriteAttributeInteger('SignalMessageCounter', -1);
        $hash = hash('sha256', json_encode([$this->ReadPropertyString('Host'), $this->ReadPropertyInteger('Port'),
            $this->ReadPropertyString('Username'), $this->ReadPropertyString('Password')], JSON_THROW_ON_ERROR));
        if ($hash !== $this->ReadAttributeString('ConfigurationHash')) {
            $this->WriteAttributeString('Snapshot', '{}');
            $this->WriteAttributeString('ConfigurationHash', $hash);
            $this->SetValue('PowerState', 'unknown');
        }
        $this->WriteAttributeString('Unsupported', '[]');
        $this->WriteAttributeString('Generation', bin2hex(random_bytes(8)));
        $this->EnableAction('Power');
        foreach (['Volume', 'Mute', 'Ambilight'] as $ident) { $this->DisableAction($ident); }
        $code = $this->ConfigurationStatus();
        $this->SetStatus($code);
        $this->SetValue('Online', false);
        $this->SetValue('LastError', $code === 104 ? 'Deaktiviert.' : ($code === 102 ? 'Noch keine aktuelle Abfrage.' : 'Konfiguration prüfen.'));
        $this->Publish();
        if ($code === 102 && (!$following || $this->CombinedSourcesState() === 'active')) {
            $this->SetTimerInterval('Poll', 1000);
        }
        $this->MaintainPairing();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($this->ReadPropertyBoolean('FollowSources')) {
            $sources = [(int)$this->ReadPropertyInteger('ShieldSignal'), (int)$this->ReadPropertyInteger('AVRSignal')];
            if ($Message !== VM_UPDATE || !in_array($SenderID, $sources, true) || !($Data[1] ?? false)) { return; }
            $previous = $this->ReadAttributeString('SourcesState');
            $current = $this->CombinedSourcesState();
            $this->WriteAttributeString('SourcesState', $current);
            if ($this->ConfigurationStatus() !== 102 || $this->PairingPending()) { return; }
            if ($current === 'active') { $this->SetTimerInterval('Poll', 100); }
            elseif ($current === 'inactive') { $this->SetTimerInterval('Poll', 0); }
            // A missing or unknown input must never trigger a Standby command.
            if ($previous === 'unknown' || $current === 'unknown' || $previous === $current) { return; }
            $on = $previous === 'inactive' && $current === 'active';
            $off = $previous === 'active' && $current === 'inactive';
            if ($on || $off) {
                try {
                    $this->RequestAction('Power', $on);
                    if ($off) { $this->SetTimerInterval('Poll', 0); }
                } catch (Throwable $e) {
                    $this->SetValue('LastError', 'Zuspieler-Schaltbefehl fehlgeschlagen: ' . $e->getMessage());
                    if ($off) { $this->SetTimerInterval('Poll', 0); }
                    $this->Publish();
                }
            }
            return;
        }
        $signalID = $this->ReadPropertyInteger('WakeSignal');
        if ($signalID <= 0 || !IPS_VariableExists($signalID) || $Message !== VM_UPDATE || $SenderID !== $signalID || !($Data[1] ?? false)) { return; }
        $previous = $this->ReadAttributeString('SignalState');
        $current = $this->SignalState($Data[0] ?? null);
        if ($this->ReadPropertyBoolean('SignalPowerOn') || $this->ReadPropertyBoolean('SignalPowerOff')) {
            // Symcon's MessageSink timestamp is a sequence counter, not Unix time.
            if ($TimeStamp <= $this->ReadAttributeInteger('SignalMessageCounter')) { return; }
            $this->WriteAttributeInteger('SignalMessageCounter', $TimeStamp);
            // Read the current source to avoid executing an obsolete queued message.
            try { $current = $this->ReadSignalState($signalID); }
            catch (Throwable $e) { $current = 'unknown'; }
        }
        $this->WriteAttributeString('SignalState', $current);
        if ($current === 'unknown' || $this->ConfigurationStatus() !== 102 || $this->PairingPending()) { return; }
        if ($current === 'active') { $this->SetTimerInterval('Poll', 100); }
        // Unknown states break the transition chain. Recovery alone is not a power command.
        $on = $previous === 'inactive' && $current === 'active' && $this->ReadPropertyBoolean('SignalPowerOn');
        $off = $previous === 'active' && $current === 'inactive' && $this->ReadPropertyBoolean('SignalPowerOff');
        if ($on || $off) {
            try { $this->RequestAction('Power', $on); }
            catch (Throwable $e) {
                $this->SetValue('LastError', 'Signal-Schaltbefehl fehlgeschlagen: ' . $e->getMessage());
                $this->SendDebug('TV-Signal', $this->GetValue('LastError'), 0);
                $this->Publish();
            }
        }
    }

    private function SignalState(mixed $value): string
    {
        if ($value === true || $value === 1) { return 'active'; }
        if ($value === false || $value === 0) { return 'inactive'; }
        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                'on', 'awake' => 'active',
                'off', 'standby' => 'inactive',
                default => 'unknown'
            };
        }
        return 'unknown';
    }

    private function CombinedSourcesState(): string
    {
        $shield = $this->ReadPropertyInteger('ShieldSignal');
        $avr = $this->ReadPropertyInteger('AVRSignal');
        if ($shield <= 0 || $avr <= 0 || !IPS_VariableExists($shield) || !IPS_VariableExists($avr)) { return 'unknown'; }
        try {
            $a = $this->SignalState(GetValue($shield));
            $b = $this->SignalState(GetValue($avr));
            if ($a === 'active' || $b === 'active') { return 'active'; }
            if ($a === 'inactive' && $b === 'inactive') { return 'inactive'; }
        } catch (Throwable $e) { return 'unknown'; }
        return 'unknown';
    }

    private function ReadSignalState(int $id): string
    {
        $variable = IPS_GetVariable($id);
        $updated = (int)($variable['VariableUpdated'] ?? 0);
        if ($updated <= 0 || time() - $updated > 150 || $updated > time() + 5) { return 'unknown'; }
        return $this->SignalState(GetValue($id));
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode((string)file_get_contents(__DIR__ . '/form.json'), true, 32, JSON_THROW_ON_ERROR);
        $captions = $this->DiagnosticCaptions();
        foreach ($form['actions'] as &$action) {
            $name = $action['name'] ?? '';
            if (isset($captions[$name])) { $action['caption'] = $captions[$name]; }
        }
        unset($action);
        return json_encode($form, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function DiagnosticCaptions(): array
    {
        return [
            'ConnectionDiagnostic' => 'Verbindungsdiagnose: ' . ($this->GetValue('LastError') ?: 'Kein aktueller Fehler gemeldet.'),
            'TransportDiagnostic' => 'Letzte HTTPS-Anfrage: ' . ($this->GetValue('TransportInfo') ?: 'Noch keine Anfrage protokolliert.'),
            'CommandDiagnostic' => 'Letzter Schaltversuch: ' . ($this->GetValue('LastCommand') ?: 'Noch kein Schaltversuch protokolliert.'),
            'PairingDiagnostic' => 'TV-Kopplung: ' . ($this->GetValue('PairingStatus') ?: 'Vorhandene Zugangsdaten verwenden oder Kopplung starten.')
        ];
    }

    private function ConfigurationStatus(): int
    {
        if (!$this->ReadPropertyBoolean('Enabled')) { return 104; }
        $connection = $this->ConnectionConfigurationStatus();
        if ($connection !== 102) { return $connection; }
        if ($this->ReadPropertyString('Username') === '' || $this->ReadPropertyString('Password') === '') { return 200; }
        return 102;
    }

    private function ConnectionConfigurationStatus(): int
    {
        $host = trim($this->ReadPropertyString('Host'));
        if ($host === '' || (filter_var($host, FILTER_VALIDATE_IP) === false
            && !preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/iD', $host))
            || $this->ReadPropertyInteger('Port') < 1 || $this->ReadPropertyInteger('Port') > 65535) { return 200; }
        return function_exists('curl_init') ? 102 : 203;
    }

    /** One bounded request, no redirects, no command retries, no credentials in diagnostics. */
    protected function Api(string $method, string $endpoint, ?array $body = null, ?array $credentials = null): array
    {
        $host = trim($this->ReadPropertyString('Host'));
        if (str_contains($host, ':')) { $host = '[' . $host . ']'; }
        $url = 'https://' . $host . ':' . $this->ReadPropertyInteger('Port') . '/6/' . $endpoint;
        $curl = curl_init($url);
        if ($curl === false) { throw new RuntimeException('TV-Verbindung konnte nicht gestartet werden.'); }
        $timeout = max(500, min(10000, $this->ReadPropertyInteger('Timeout')));
        $credentials ??= ['username' => $this->ReadPropertyString('Username'), 'password' => $this->ReadPropertyString('Password')];
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT_MS => $timeout,
            CURLOPT_TIMEOUT_MS => $timeout, CURLOPT_SSL_VERIFYPEER => $this->ReadPropertyBoolean('VerifyTLS'),
            CURLOPT_SSL_VERIFYHOST => $this->ReadPropertyBoolean('VerifyTLS') ? 2 : 0,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CUSTOMREQUEST => $method];
        if (($credentials['username'] ?? '') !== '' && ($credentials['password'] ?? '') !== '') {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_DIGEST;
            $options[CURLOPT_USERNAME] = $credentials['username'];
            $options[CURLOPT_PASSWORD] = $credentials['password'];
        }
        curl_setopt_array($curl, $options);
        if ($body !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR)); }
        try {
            $started = microtime(true);
            $raw = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $errno = curl_errno($curl);
            $info = $method . ' /6/' . $endpoint . ' · HTTP ' . $status . ' · cURL ' . $errno . ' · '
                . (int)round((microtime(true) - $started) * 1000) . ' ms';
            $this->SetValue('TransportInfo', $info);
            // Log only fixed metadata, never curl_error(), headers, bodies or credentials.
            $this->SendDebug('TV-Transport', $info, 0);
            if ($raw === false) {
                $reason = match ($errno) {
                    5, 6 => 'Hostname konnte nicht aufgelöst werden.',
                    7 => 'Verbindung zum TV-Port konnte nicht hergestellt werden.',
                    28 => 'Zeitüberschreitung nach maximal ' . $timeout . ' ms.',
                    35 => 'TLS-Verbindungsaufbau fehlgeschlagen.',
                    51, 60 => 'TLS-Zertifikat des Fernsehers konnte nicht bestätigt werden.',
                    52 => 'TV hat die Verbindung ohne HTTP-Antwort geschlossen.',
                    55, 56 => 'TV-Verbindung beim Senden oder Empfangen abgebrochen.',
                    default => 'HTTPS-Anfrage fehlgeschlagen.'
                };
                throw new RuntimeException($reason . ' [' . $info . ']', 201);
            }
            if ($status === 401 || $status === 403) { throw new RuntimeException('TV-Anmeldung fehlgeschlagen (HTTP ' . $status . '). Kopplungsdaten prüfen.', 202); }
            if ($status < 200 || $status >= 300) { throw new RuntimeException('TV-API ' . $endpoint . ': HTTP ' . $status, $status); }
            // Some firmware acknowledges POST with empty text or an HTML "Ok" page.
            if ($method === 'POST' && (trim($raw) === '' || preg_match('/^\s*<html>.*\bOk\b.*<\/html>\s*$/is', $raw))) { return []; }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $kind = trim($raw) === '' ? 'leere Antwort' : (str_starts_with(ltrim($raw), '<') ? 'HTML statt JSON' : 'kein gültiges JSON-Objekt');
                throw new RuntimeException('TV-API ' . $endpoint . ': ' . $kind . ' (HTTP ' . $status . ').', 201);
            }
            if (isset($data['error']) || (isset($data['error_id']) && $data['error_id'] !== 'SUCCESS')) {
                throw new RuntimeException('TV-API ' . $endpoint . ': Befehl abgewiesen.', 201);
            }
            return $data;
        } finally { curl_close($curl); }
    }

    private function Snapshot(): array
    {
        return json_decode($this->ReadAttributeString('Snapshot'), true, 32, JSON_THROW_ON_ERROR);
    }

    public function GetState(): string
    {
        $s = $this->Snapshot();
        $enabled = $this->ConfigurationStatus() === 102;
        $paused = $enabled && ($s['power'] ?? '') === 'standby' && $this->ReadPropertyInteger('StandbyInterval') === 0;
        $interval = ($s['power'] ?? '') === 'standby' ? $this->ReadPropertyInteger('StandbyInterval') : $this->ReadPropertyInteger('PollInterval');
        $stale = !$paused && time() - (int)($s['updated'] ?? 0) > max(45, $interval * 3);
        $s += ['power' => 'unknown', 'updated' => 0, 'volume' => null, 'mute' => null, 'activity' => '',
            'ambilight' => null, 'ambilightMode' => '', 'caps' => []];
        $s['online'] = $enabled && !$stale && !$paused && $this->GetValue('Online');
        $s['paused'] = $paused;
        $s['stale'] = $stale;
        $s['enabled'] = $enabled;
        $s['error'] = $this->GetValue('LastError');
        $s['lastCommand'] = $this->GetValue('LastCommand');
        if (!$enabled || $stale || (!$s['online'] && !$paused)) {
            $s['power'] = 'unknown'; $s['caps'] = [];
            $s['volume'] = null; $s['mute'] = null; $s['activity'] = ''; $s['ambilight'] = null; $s['ambilightMode'] = '';
        }
        if (!$enabled) { $s['caps'] = []; }
        $s['kind'] = 'tv'; $s['direct'] = true; $s['device'] = 'philips';
        return json_encode($s, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function Publish(): void
    {
        $state = $this->GetState();
        $this->SetValue('State', $state);
        foreach ($this->DiagnosticCaptions() as $name => $caption) { $this->UpdateFormField($name, 'caption', $caption); }
        $s = json_decode($state, true, 32, JSON_THROW_ON_ERROR);
        foreach (['Volume' => 'audio', 'Mute' => 'audio', 'Ambilight' => 'ambilight'] as $ident => $cap) {
            if ($s['online'] && $s['power'] === 'awake' && ($s['caps'][$cap] ?? false)) { $this->EnableAction($ident); }
            else { $this->DisableAction($ident); }
        }
    }

    public function ManualUpdate(): void
    {
        if ($this->ConfigurationStatus() !== 102 || $this->PairingPending()) { return; }
        $this->WriteAttributeBoolean('ManualRefreshPending', true);
        $this->SetTimerInterval('Poll', 100);
    }

    public function UpdateStatus(): void
    {
        $lock = 'SVPTVPoll' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 0)) { return; }
        try { $this->PollStatus(); }
        finally { IPS_SemaphoreLeave($lock); }
    }

    private function PollStatus(): void
    {
        $this->SetTimerInterval('Poll', 0);
        $force = $this->ReadAttributeBoolean('ManualRefreshPending');
        $this->WriteAttributeBoolean('ManualRefreshPending', false);
        if (!$force && $this->ReadPropertyBoolean('FollowSources') && $this->CombinedSourcesState() !== 'active') { return; }
        if ($this->PairingPending()) { return; }
        if ($this->ConfigurationStatus() !== 102) { $this->SetValue('Online', false); $this->Publish(); return; }
        $generation = $this->ReadAttributeString('Generation');
        $s = ['caps' => [], 'volume' => null, 'mute' => null, 'ambilight' => null, 'ambilightMode' => '', 'activity' => ''];
        $warnings = [];
        try {
            $power = $this->Api('GET', 'powerstate');
            if (!in_array($power['powerstate'] ?? null, ['On', 'Standby'], true)) { throw new RuntimeException('Ungültiger TV-Betriebszustand.', 201); }
            $s['power'] = $power['powerstate'] === 'On' ? 'awake' : 'standby';
            if ($s['power'] === 'awake') {
                foreach (['audio/volume', 'activities/current', 'ambilight/power', 'ambilight/currentconfiguration'] as $endpoint) {
                    // A user command supersedes an in-flight background snapshot.
                    if ($generation !== $this->ReadAttributeString('Generation')) { return; }
                    $unsupported = json_decode($this->ReadAttributeString('Unsupported'), true, 16, JSON_THROW_ON_ERROR);
                    if (in_array($endpoint, $unsupported, true)) { continue; }
                    try {
                        $data = $this->Api('GET', $endpoint);
                        if ($endpoint === 'audio/volume') {
                            if (!isset($data['current'], $data['min'], $data['max']) || !is_numeric($data['current'])
                                || !is_numeric($data['min']) || !is_numeric($data['max']) || $data['max'] <= $data['min']
                                || !is_bool($data['muted'] ?? null) || $data['current'] < $data['min'] || $data['current'] > $data['max']) {
                                throw new RuntimeException('Ungültige TV-Lautstärke.');
                            }
                            $s['audio'] = $data;
                            $s['volume'] = (int)round(100 * ($data['current'] - $data['min']) / ($data['max'] - $data['min']));
                            $s['mute'] = $data['muted']; $s['caps']['audio'] = true;
                        } elseif ($endpoint === 'activities/current') {
                            $component = $data['component'] ?? $data['intent']['component'] ?? [];
                            $s['activity'] = (string)($data['extras']['uri'] ?? $data['intent']['extras']['uri'] ?? $component['packageName'] ?? '');
                            $s['caps']['activity'] = true;
                        } elseif ($endpoint === 'ambilight/power') {
                            if (!in_array($data['power'] ?? null, ['On', 'Off'], true)) { throw new RuntimeException('Ungültiger Ambilight-Status.'); }
                            $s['ambilight'] = $data['power'] === 'On'; $s['caps']['ambilight'] = true;
                        } else { $s['ambilightMode'] = (string)($data['styleName'] ?? ''); }
                    } catch (Throwable $e) {
                        if ($e->getCode() === 202) { throw $e; }
                        if (in_array($e->getCode(), [404, 405, 501], true)) {
                            $unsupported[] = $endpoint;
                            $this->WriteAttributeString('Unsupported', json_encode(array_unique($unsupported), JSON_THROW_ON_ERROR));
                        } else { $warnings[] = $endpoint . ': Status momentan nicht verfügbar.'; }
                    }
                }
            }
            if ($generation !== $this->ReadAttributeString('Generation')) { return; }
            $s['updated'] = time();
            $this->WriteAttributeString('Snapshot', json_encode($s, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            $this->SetValue('Online', true);
            $this->SetValue('Power', $s['power'] === 'awake');
            $this->SetValue('PowerState', $s['power']);
            foreach (['Volume' => 'volume', 'Mute' => 'mute', 'Ambilight' => 'ambilight', 'Activity' => 'activity', 'AmbilightMode' => 'ambilightMode'] as $ident => $key) {
                if ($s[$key] !== null) { $this->SetValue($ident, $s[$key]); }
            }
            $this->SetValue('Updated', $s['updated']);
            $this->SetValue('LastError', implode(' ', $warnings));
            $this->SetStatus(102);
        } catch (Throwable $e) {
            if ($generation !== $this->ReadAttributeString('Generation')) { return; }
            $this->SetValue('Online', false);
            $this->SetValue('PowerState', 'unknown');
            // A failed read must not retain the standby pause and suppress recovery.
            $s = $this->Snapshot(); $s['power'] = 'unknown'; $s['caps'] = [];
            $this->WriteAttributeString('Snapshot', json_encode($s, JSON_THROW_ON_ERROR));
            $this->SetValue('LastError', $e->getMessage());
            $this->SetStatus($e->getCode() === 202 ? 202 : 201);
        }
        $this->Publish();
        $standby = ($this->Snapshot()['power'] ?? '') === 'standby';
        $interval = $standby ? max(0, min(3600, $this->ReadPropertyInteger('StandbyInterval')))
            : max(5, min(300, $this->ReadPropertyInteger('PollInterval')));
        if ($this->ReadPropertyBoolean('FollowSources')) { $interval = $this->CombinedSourcesState() === 'active' ? 30 : 0; }
        $this->SetTimerInterval('Poll', $interval * 1000);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->PairingPending()) { throw new RuntimeException('Bitte zuerst die laufende TV-Kopplung abschließen.'); }
        if ($this->ConfigurationStatus() !== 102) { throw new RuntimeException('Philips-TV-Verbindung zuerst konfigurieren und aktivieren.'); }
        if ($Ident === 'Refresh') {
            if ($Value !== true) { throw new InvalidArgumentException('Aktualisieren erwartet true.'); }
            $this->ManualUpdate();
            return;
        }
        $lock = 'SVPTVCommand' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 100)) { throw new RuntimeException('TV-Befehl läuft bereits.'); }
        $attempt = null;
        try {
            $body = []; $endpoint = '';
            $s = json_decode($this->GetState(), true, 32, JSON_THROW_ON_ERROR);
            switch ($Ident) {
                case 'Power':
                    if (!is_bool($Value)) { throw new InvalidArgumentException('Ein/Aus erwartet true oder false.'); }
                    $endpoint = 'powerstate'; $body = ['powerstate' => $Value ? 'On' : 'Standby']; break;
                case 'Volume':
                case 'Mute':
                    if (!($s['caps']['audio'] ?? false) || !$s['online'] || $s['power'] !== 'awake') { throw new RuntimeException('Zuerst aktuellen Audio-Status abfragen.'); }
                    $audio = $s['audio'];
                    if ($Ident === 'Volume') {
                        if (!is_int($Value) || $Value < 0 || $Value > 100) { throw new InvalidArgumentException('Lautstärke muss 0 bis 100 sein.'); }
                        $audio['current'] = (int)round($audio['min'] + $Value / 100 * ($audio['max'] - $audio['min']));
                    } else {
                        if (!is_bool($Value)) { throw new InvalidArgumentException('Stumm erwartet true oder false.'); }
                        $audio['muted'] = $Value;
                    }
                    $endpoint = 'audio/volume'; $body = ['current' => $audio['current'], 'muted' => $audio['muted']]; break;
                case 'Ambilight':
                    if (!is_bool($Value)) { throw new InvalidArgumentException('Ambilight erwartet true oder false.'); }
                    if (!($s['caps']['ambilight'] ?? false) || !$s['online'] || $s['power'] !== 'awake') { throw new RuntimeException('Ambilight derzeit nicht verfügbar.'); }
                    $endpoint = 'ambilight/power'; $body = ['power' => $Value ? 'On' : 'Off']; break;
                case 'RemoteKey':
                    if (!is_string($Value) || !in_array($Value, self::KEYS, true)) { throw new InvalidArgumentException('Unbekannte Fernbedienungstaste.'); }
                    $endpoint = 'input/key'; $body = ['key' => $Value]; break;
                default: throw new InvalidArgumentException('Unbekannter Philips-TV-Befehl.');
            }
            $this->WriteAttributeString('Generation', bin2hex(random_bytes(8)));
            // No status GET before the command and no automatic replay on a timeout.
            $attempt = date('c') . ' · ' . $Ident . ' · POST /6/' . $endpoint;
            $this->SetValue('LastCommand', $attempt . ' · Versand läuft.');
            $this->Api('POST', $endpoint, $body);
            $this->SetValue('LastCommand', $attempt . ' · TV hat den Empfang bestätigt; Ausführung noch nicht bestätigt.');
            if ($endpoint === 'audio/volume') {
                // Preserve the other half of the last accepted audio command during rapid commands.
                // Displayed volume/mute remain last observed until the independent confirmation GET.
                $pending = $this->Snapshot();
                $pending['audio']['current'] = $body['current'];
                $pending['audio']['muted'] = $body['muted'];
                $this->WriteAttributeString('Snapshot', json_encode($pending, JSON_THROW_ON_ERROR));
            }
            $this->SetValue('LastError', 'Befehl gesendet; Statusbestätigung folgt.');
            $this->SetTimerInterval('Poll', 700);
        } catch (Throwable $e) {
            if ($attempt !== null) { $this->SetValue('LastCommand', $attempt . ' · Fehlgeschlagen: ' . $e->getMessage()); }
            $this->SetValue('LastError', $e->getMessage());
            $this->SetTimerInterval('Poll', 1000);
            throw $e;
        } finally {
            $this->Publish();
            IPS_SemaphoreLeave($lock);
        }
    }
}
