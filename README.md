# Philips TV für IP-Symcon

Eigenständiges Philips-TV-Modul **v1.0.0 für IP-Symcon 9.0 oder neuer**, mit direkter JointSpace-Verbindung über HTTPS/API 6 und Digest-Anmeldung. Keine laufende Python- oder Node-RED-Verbindung erforderlich. Vorhandene Kopplungsdaten können weiterverwendet werden.

## Installation

1. Unter **Modul Control → Hinzufügen** dieses Repository eintragen:

   `https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon`

2. Eine Instanz **Philips TV (JointSpace direkt)** erstellen.
3. IP-Adresse und die bereits erzeugten Kopplungsdaten eintragen, Verbindung aktivieren und Änderungen übernehmen.
4. Bei eingeschaltetem Fernseher **Status aktualisieren / Verbindung prüfen** drücken.

Das Modul funktioniert unabhängig von der Haussteuerung. Für Anzeige und Bedienung in [Symcon-Hausstatus](https://github.com/Powermaniaxx/Symcon-Hausstatus) dort auf **v0.132 oder neuer** aktualisieren und in der zentralen Hausstatus-Instanz unter **Wohnzimmer → Direkte Philips-TV-Instanz** diese Instanz auswählen.

## Funktionen

- TV ein/aus mit ausdrücklichen Befehlen.
- Lautstärke, Stummschaltung und Ambilight ein/aus, soweit vom TV unterstützt.
- Betriebszustand, aktive Anwendung bzw. Eingangs-URI und Ambilight-Modus.
- Fernbedienung über Instanzaktionen und in der Hausstatus-Kachel.
- Unmittelbarer Befehlsversand und getrennte Statusbestätigung.
- Konfigurierbare Abfragen, Standby-Pause und optionales Wake-Signal vom Shield-Betriebszustand.

Nach bestätigtem Standby pausieren Abfragen standardmäßig. Ein Ein-Befehl, manuelles Aktualisieren oder ein passendes Wake-Signal startet sie wieder. Wer mit der TV-Fernbedienung einschaltet, kann alternativ Standby-Abfragen aktivieren. Einschalten per API setzt voraus, dass der TV im Standby über das Netzwerk erreichbar bleibt.

Benutzername und Passwort bleiben in der lokalen Symcon-Konfiguration. Die bereits erzeugten API-Zugangsdaten werden benötigt; die TV-PIN dient nicht als Kennwort. Für dieses Modul wird keine erneute Kopplung ausgelöst.

[Vollständige Einrichtung, Variablen und Skriptbefehle](docs/Einrichtung.md) · [Prüfstand und noch ausstehender Live-Test](docs/Pruefung.md)

## Entwicklung

```sh
php tests/philips-direct.php
php -n tests/philips-transport.php
python tests/repository-structure.py
```

Der Modultest benötigt die cURL-Erweiterung, verwendet jedoch eine nachgebildete TV-API. Der Transporttest läuft ohne geladene cURL-Erweiterung und bildet deren Funktionen nach. Es werden keine echten TV-Befehle gesendet. Ein Test auf dem konkreten Fernseher und in einer echten Symcon-Installation steht noch aus.
