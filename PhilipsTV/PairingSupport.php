<?php
declare(strict_types=1);

trait PhilipsTVPairingSupport
{
    // Public JointSpace protocol key, shared by Philips remote clients; not a user credential.
    private const PAIRING_KEY = 'ZmVay1EQVFOaZhwQ4Kv81ypLAZNczV9sG4KkseXWn1NEk6cXmPKO/MCa9sryslvLCFMnNe4Z4CPXzToowvhHvA==';

    private function CreatePairing(): void
    {
        $this->RegisterAttributeString('PairingSession', '');
        $this->RegisterVariableString('PairingStatus', 'TV-Kopplung', '', 140);
        $this->RegisterTimer('PairingTimeout', 0, 'SVPTV_ExpirePairing($_IPS["TARGET"]);');
    }

    private function PairingSession(): array
    {
        $raw = $this->ReadAttributeString('PairingSession');
        return $raw === '' ? [] : (json_decode($raw, true, 32, JSON_THROW_ON_ERROR) ?: []);
    }

    private function PairingPending(): bool
    {
        $s = $this->PairingSession();
        return $s !== [] && (int)($s['expires'] ?? 0) > time();
    }

    private function ResumeAfterPairing(): void
    {
        $standby = ($this->Snapshot()['power'] ?? '') === 'standby' && $this->ReadPropertyInteger('StandbyInterval') === 0;
        $this->SetTimerInterval('Poll', $this->ConfigurationStatus() === 102 && !$standby ? 1000 : 0);
        $this->Publish();
    }

    private function ClearPairing(): void
    {
        $this->WriteAttributeString('PairingSession', '');
        $this->SetTimerInterval('PairingTimeout', 0);
        $this->UpdateFormField('PairingPIN', 'value', '');
    }

    private function MaintainPairing(): void
    {
        $s = $this->PairingSession();
        if ($s === []) { return; }
        if (($s['host'] ?? '') !== trim($this->ReadPropertyString('Host')) || ($s['port'] ?? 0) !== $this->ReadPropertyInteger('Port')) {
            $this->ClearPairing();
            $this->SetValue('PairingStatus', 'TV-Adresse geändert. Kopplung bitte neu starten.');
        } elseif (!$this->PairingPending()) { $this->ExpirePairing(); }
        else {
            $this->SetTimerInterval('Poll', 0);
            $this->SetTimerInterval('PairingTimeout', max(1, $s['expires'] - time()) * 1000);
        }
    }

    public function StartPairing(): void
    {
        if ($this->ConnectionConfigurationStatus() !== 102) { throw new RuntimeException('TV-Adresse/HTTPS-Port speichern und cURL-Verfügbarkeit prüfen.'); }
        $lock = 'SVPTVCommand' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 100)) { throw new RuntimeException('TV-Anfrage läuft bereits.'); }
        try {
            $this->WriteAttributeString('Generation', bin2hex(random_bytes(8)));
            $this->SetTimerInterval('Poll', 0);
            $this->ClearPairing();
            $device = ['id' => bin2hex(random_bytes(16)), 'device_name' => 'IP-Symcon Philips TV',
                'device_os' => 'Linux', 'app_id' => 'symcon.philipstv', 'app_name' => 'Philips TV for Symcon', 'type' => 'native'];
            // Initial pairing is explicitly unauthenticated; never use an old pairing here.
            $reply = $this->Api('POST', 'pair/request', ['access' => ['scope' => ['read', 'write', 'control']], 'device' => $device], []);
            if (($reply['error_id'] ?? '') !== 'SUCCESS' || !is_string($reply['auth_key'] ?? null) || $reply['auth_key'] === ''
                || !isset($reply['timestamp']) || (!is_int($reply['timestamp']) && !is_string($reply['timestamp']))) {
                throw new RuntimeException('TV hat keine gültige Kopplungsanforderung bestätigt.');
            }
            $session = ['device' => $device, 'auth_key' => $reply['auth_key'], 'timestamp' => $reply['timestamp'],
                'expires' => time() + 120, 'host' => trim($this->ReadPropertyString('Host')), 'port' => $this->ReadPropertyInteger('Port')];
            $this->WriteAttributeString('PairingSession', json_encode($session, JSON_THROW_ON_ERROR));
            $this->SetTimerInterval('PairingTimeout', 120000);
            $this->SetValue('PairingStatus', 'PIN am Fernseher ablesen, unten eingeben und innerhalb von 2 Minuten bestätigen.');
            $this->Publish();
        } catch (Throwable $e) {
            $this->ClearPairing();
            $this->SetValue('PairingStatus', 'Kopplung nicht gestartet: ' . $e->getMessage());
            $this->ResumeAfterPairing();
            throw $e;
        } finally { IPS_SemaphoreLeave($lock); }
    }

    public function CompletePairing(string $PIN): void
    {
        $PIN = trim($PIN);
        if (preg_match('/^[0-9]{4,8}$/D', $PIN) !== 1) { throw new InvalidArgumentException('Die 4- bis 8-stellige PIN vom TV eingeben.'); }
        $lock = 'SVPTVCommand' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 100)) { throw new RuntimeException('TV-Anfrage läuft bereits.'); }
        try {
            $s = $this->PairingSession();
            if (!$this->PairingPending()) { throw new RuntimeException('Kopplung fehlt oder ist abgelaufen. Kopplung neu starten.'); }
            if (($s['host'] ?? '') !== trim($this->ReadPropertyString('Host')) || ($s['port'] ?? 0) !== $this->ReadPropertyInteger('Port')) {
                throw new RuntimeException('TV-Adresse geändert. Kopplung neu starten.');
            }
            $key = base64_decode(self::PAIRING_KEY, true);
            $signature = base64_encode(hash_hmac('sha1', (string)$s['timestamp'] . $PIN, $key, true));
            $auth = ['auth_appId' => '1', 'auth_timestamp' => $s['timestamp'], 'pin' => $PIN, 'auth_signature' => $signature];
            $reply = $this->Api('POST', 'pair/grant', ['auth' => $auth, 'device' => $s['device']],
                ['username' => $s['device']['id'], 'password' => $s['auth_key']]);
            if (($reply['error_id'] ?? '') !== 'SUCCESS') { throw new RuntimeException('TV hat die PIN nicht bestätigt. Kopplung neu starten.'); }
            // Existing credentials remain untouched unless the TV confirms the grant.
            $this->ClearPairing();
            IPS_SetProperty($this->InstanceID, 'Username', $s['device']['id']);
            IPS_SetProperty($this->InstanceID, 'Password', $s['auth_key']);
            IPS_SetProperty($this->InstanceID, 'Enabled', true);
            $this->SetValue('PairingStatus', 'Kopplung erfolgreich. Zugangsdaten lokal gespeichert und Verbindung aktiviert.');
            IPS_ApplyChanges($this->InstanceID);
            $this->UpdateFormField('Username', 'value', $s['device']['id']);
            // Synchronize the user's own masked credential fields to avoid stale form saves.
            $this->UpdateFormField('Password', 'value', $s['auth_key']);
            $this->UpdateFormField('Enabled', 'value', true);
            $this->Publish();
        } catch (Throwable $e) {
            $this->ClearPairing();
            $this->SetValue('PairingStatus', 'Kopplung fehlgeschlagen: ' . $e->getMessage());
            $this->ResumeAfterPairing();
            throw $e;
        } finally { IPS_SemaphoreLeave($lock); }
    }

    public function ExpirePairing(): void
    {
        $s = $this->PairingSession();
        if ($s !== [] && (int)($s['expires'] ?? 0) > time()) {
            $this->SetTimerInterval('PairingTimeout', max(1, $s['expires'] - time()) * 1000);
            return;
        }
        $this->ClearPairing();
        if ($s !== []) { $this->SetValue('PairingStatus', 'PIN-Zeit abgelaufen. Kopplung bei Bedarf neu starten.'); }
        $this->ResumeAfterPairing();
    }
}
