<?php
declare(strict_types=1);

class PhilipsTV extends IPSModuleStrict
{
    private const KEYS = ['Home', 'Back', 'Find', 'Options', 'Source', 'Info', 'WatchTV',
        'CursorUp', 'CursorDown', 'CursorLeft', 'CursorRight', 'Confirm',
        'PlayPause', 'Play', 'Pause', 'Stop', 'Rewind', 'FastForward',
        'ChannelStepUp', 'ChannelStepDown', 'VolumeUp', 'VolumeDown', 'Mute',
        'RedColour', 'GreenColour', 'YellowColour', 'BlueColour',
        'Digit0', 'Digit1', 'Digit2', 'Digit3', 'Digit4', 'Digit5', 'Digit6', 'Digit7', 'Digit8', 'Digit9'];

    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean('Enabled', false);
        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('Port', 1926);
        $this->RegisterPropertyInteger('PollInterval', 15);
        $this->RegisterPropertyInteger('StandbyInterval', 0);
        $this->RegisterPropertyInteger('WakeSignal', 0);
        $this->RegisterPropertyInteger('Timeout', 2000);
        $this->RegisterPropertyBoolean('VerifyTLS', false);
        $this->RegisterAttributeString('Snapshot', '{}');
        $this->RegisterAttributeString('Unsupported', '[]');
        $this->RegisterAttributeString('Generation', '');
        $this->RegisterAttributeString('ConfigurationHash', '');
        $this->RegisterAttributeInteger('WakeSubscription', 0);
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
        $this->RegisterTimer('Poll', 0, 'SVPTV_UpdateStatus($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('Poll', 0);
        $old = $this->ReadAttributeInteger('WakeSubscription');
        if ($old > 0) { $this->UnregisterMessage($old, VM_UPDATE); }
        $wake = $this->ReadPropertyInteger('WakeSignal');
        if ($wake > 0 && IPS_VariableExists($wake)) { $this->RegisterMessage($wake, VM_UPDATE); }
        $this->WriteAttributeInteger('WakeSubscription', $wake);
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
        if ($code === 102) { $this->SetTimerInterval('Poll', 1000); }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message !== VM_UPDATE || $SenderID !== $this->ReadPropertyInteger('WakeSignal') || !($Data[1] ?? false)) { return; }
        $value = $Data[0] ?? null;
        if ($value === true || (is_string($value) && in_array(strtolower($value), ['awake', 'on'], true))) {
            if ($this->ConfigurationStatus() === 102) { $this->SetTimerInterval('Poll', 100); }
        }
    }

    private function ConfigurationStatus(): int
    {
        if (!$this->ReadPropertyBoolean('Enabled')) { return 104; }
        $host = trim($this->ReadPropertyString('Host'));
        if ($host === '' || (filter_var($host, FILTER_VALIDATE_IP) === false
            && !preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/iD', $host))
            || $this->ReadPropertyString('Username') === '' || $this->ReadPropertyString('Password') === ''
            || $this->ReadPropertyInteger('Port') < 1 || $this->ReadPropertyInteger('Port') > 65535) { return 200; }
        return function_exists('curl_init') ? 102 : 203;
    }

    /** One bounded request, no redirects, no command retries, no credentials in diagnostics. */
    protected function Api(string $method, string $endpoint, ?array $body = null): array
    {
        $host = trim($this->ReadPropertyString('Host'));
        if (str_contains($host, ':')) { $host = '[' . $host . ']'; }
        $url = 'https://' . $host . ':' . $this->ReadPropertyInteger('Port') . '/6/' . $endpoint;
        $curl = curl_init($url);
        if ($curl === false) { throw new RuntimeException('TV-Verbindung konnte nicht gestartet werden.'); }
        $timeout = max(500, min(5000, $this->ReadPropertyInteger('Timeout')));
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST, CURLOPT_USERNAME => $this->ReadPropertyString('Username'),
            CURLOPT_PASSWORD => $this->ReadPropertyString('Password'), CURLOPT_CONNECTTIMEOUT_MS => $timeout,
            CURLOPT_TIMEOUT_MS => $timeout, CURLOPT_SSL_VERIFYPEER => $this->ReadPropertyBoolean('VerifyTLS'),
            CURLOPT_SSL_VERIFYHOST => $this->ReadPropertyBoolean('VerifyTLS') ? 2 : 0,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CUSTOMREQUEST => $method]);
        if ($body !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR)); }
        try {
            $raw = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($raw === false) { throw new RuntimeException('TV nicht erreichbar (Verbindung/Zeitlimit).', 201); }
            if ($status === 401 || $status === 403) { throw new RuntimeException('TV-Anmeldung fehlgeschlagen. Kopplungsdaten prüfen.', 202); }
            if ($status < 200 || $status >= 300) { throw new RuntimeException('TV-API ' . $endpoint . ': HTTP ' . $status, $status); }
            // Some firmware acknowledges POST with empty text or an HTML "Ok" page.
            if ($method === 'POST' && (trim($raw) === '' || preg_match('/^\s*<html>.*\bOk\b.*<\/html>\s*$/is', $raw))) { return []; }
            $data = json_decode($raw, true);
            if (!is_array($data)) { throw new RuntimeException('TV-API ' . $endpoint . ': ungültige Antwort.', 201); }
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
        $s = json_decode($state, true, 32, JSON_THROW_ON_ERROR);
        foreach (['Volume' => 'audio', 'Mute' => 'audio', 'Ambilight' => 'ambilight'] as $ident => $cap) {
            if ($s['online'] && $s['power'] === 'awake' && ($s['caps'][$cap] ?? false)) { $this->EnableAction($ident); }
            else { $this->DisableAction($ident); }
        }
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
        $this->SetTimerInterval('Poll', $interval * 1000);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->ConfigurationStatus() !== 102) { throw new RuntimeException('Philips-TV-Verbindung zuerst konfigurieren und aktivieren.'); }
        if ($Ident === 'Refresh') {
            if ($Value !== true) { throw new InvalidArgumentException('Aktualisieren erwartet true.'); }
            $this->SetTimerInterval('Poll', 100);
            return;
        }
        $lock = 'SVPTVCommand' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 100)) { throw new RuntimeException('TV-Befehl läuft bereits.'); }
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
            $this->Api('POST', $endpoint, $body);
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
            $this->SetValue('LastError', $e->getMessage());
            $this->SetTimerInterval('Poll', 1000);
            throw $e;
        } finally {
            $this->Publish();
            IPS_SemaphoreLeave($lock);
        }
    }
}
