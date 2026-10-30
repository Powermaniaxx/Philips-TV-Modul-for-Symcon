# Philips TV für IP-Symcon

Eigenständiges Philips-TV-Modul **v1.1.0 für IP-Symcon 9.0 oder neuer**, mit direkter JointSpace-Verbindung über HTTPS/API 6 und Digest-Anmeldung. Keine laufende Python- oder Node-RED-Verbindung erforderlich. Vorhandene Kopplungsdaten können weiterverwendet werden.

## Installation

1. Unter **Modul Control → Hinzufügen** dieses Repository eintragen:

   `https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon`

2. Eine Instanz **Philips TV (JointSpace direkt)** erstellen.
3. IP-Adresse eintragen und Änderungen übernehmen. Den Fernseher mit seiner Fernbedienung einschalten.
4. Im Aktionsbereich **Kopplung starten (PIN am TV anzeigen)** drücken, die angezeigte TV-PIN eingeben und **TV-PIN bestätigen und Zugangsdaten speichern** drücken. Das Modul speichert die erzeugten Zugangsdaten und aktiviert die Verbindung automatisch.
5. **Status aktualisieren / Verbindung prüfen** drücken. Alternativ können vorhandene API-Zugangsdaten weiterhin von Hand eingetragen werden.

Das Modul funktioniert unabhängig von der Haussteuerung. Für Anzeige und Bedienung in [Symcon-Hausstatus](https://github.com/Powermaniaxx/Symcon-Hausstatus) dort auf **v0.132 oder neuer** aktualisieren und in der zentralen Hausstatus-Instanz unter **Wohnzimmer → Direkte Philips-TV-Instanz** diese Instanz auswählen.

## Funktionen

- TV ein/aus mit ausdrücklichen Befehlen.
- Lautstärke, Stummschaltung und Ambilight ein/aus, soweit vom TV unterstützt.
- Betriebszustand, aktive Anwendung bzw. Eingangs-URI und Ambilight-Modus.
- Fernbedienung über Instanzaktionen und in der Hausstatus-Kachel.
- Unmittelbarer Befehlsversand und getrennte Statusbestätigung.
- Konfigurierbare Abfragen, Standby-Pause und optionales Wake-Signal vom Shield-Betriebszustand.

Nach bestätigtem Standby pausieren Abfragen standardmäßig. Ein Ein-Befehl, manuelles Aktualisieren oder ein passendes Wake-Signal startet sie wieder. Wer mit der TV-Fernbedienung einschaltet, kann alternativ Standby-Abfragen aktivieren. Einschalten per API setzt voraus, dass der TV im Standby über das Netzwerk erreichbar bleibt.

Benutzername und Passwort bleiben in der lokalen Symcon-Konfiguration. Die bereits erzeugten API-Zugangsdaten werden benötigt; die TV-PIN dient nicht als Kennwort. Eine bestehende Kopplung bleibt erhalten, solange nicht ausdrücklich eine neue gestartet und vom TV bestätigt wird. Andere Benutzer können ihren eigenen kompatiblen Philips-TV mit JointSpace API 6 über denselben Ablauf koppeln; es sind keine fest hinterlegten Geräte-IDs oder privaten TV-Zugangsdaten erforderlich.

[Vollständige Einrichtung, Variablen und Skriptbefehle](docs/Einrichtung.md) · [Prüfstand und noch ausstehender Live-Test](docs/Pruefung.md)

## Diagnose bei Verbindungs- oder Schaltproblemen

Ab v1.0.1 stehen **Verbindungsdiagnose**, **letzte HTTPS-Anfrage** und **letzter Schaltversuch** direkt im Konfigurationsformular. Die Meldung nennt beispielsweise einen Timeout (cURL 28), eine abgewiesene Verbindung (cURL 7), einen TLS-Fehler (cURL 35/60), einen HTTP-Anmeldefehler (401/403) oder eine ungültige TV-Antwort. Zugangsdaten und Antwortinhalte werden dabei nicht ausgegeben.

Nach einem Update die Instanzkonfiguration neu öffnen. Bei laufendem TV **Status aktualisieren / Verbindung prüfen** drücken. Ein eigenständiger Testknopf öffnet auf Wunsch das **Home-Menü** am TV, ohne die Hausstatus-Kachel zu verwenden. Das letzte Schaltergebnis bleibt auch nach erfolgreichen Statusabfragen erhalten. Ein bestätigter HTTP-Empfang ist noch keine bestätigte Ausführung am Gerät.

Neue Instanzen verwenden 5000 ms Zeitlimit pro Anfrage. Bereits gespeicherte Zeitlimits bleiben erhalten; für einen ersten Test lässt sich der Wert unter **Zeitlimit je Anfrage** auf 5000 ms setzen. Die Kopplung muss dafür nicht erneuert werden. Ein Live-Test am betroffenen TV ist weiterhin erforderlich.

## Entwicklung

```sh
php tests/philips-direct.php
php tests/philips-pairing.php
php -n tests/philips-transport.php
python tests/repository-structure.py
```

Der Modultest benötigt die cURL-Erweiterung, verwendet jedoch eine nachgebildete TV-API. Der Transporttest läuft ohne geladene cURL-Erweiterung und bildet deren Funktionen nach. Es werden keine echten TV-Befehle gesendet. Ein Test auf dem konkreten Fernseher und in einer echten Symcon-Installation steht noch aus.

## Shield mit dem Philips koppeln

Das angepasste [Kopplungsskript](docs/Shield-Philips-TV-Kopplung.php) nutzt die Boolean-Aktionsvariable `Power` der in der Hausstatus-Hauptinstanz ausgewählten Philips-TV-Instanz. Nur der Shield-Zustand löst Ein/Standby aus. Ein manueller Start richtet das Ereignis ein und speichert den Ausgangszustand, ohne den TV zu schalten. [Skript einrichten](docs/Shield-Philips-TV-Kopplung.md). Dieser optionale Helfer benötigt Hausstatus; das TV-Modul selbst funktioniert eigenständig.
