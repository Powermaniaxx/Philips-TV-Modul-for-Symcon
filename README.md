# Philips TV Steuerung (JointSpace) für IP-Symcon

Eigenständiges Philips-TV-Modul **v1.4.2 für IP-Symcon 9.0 oder neuer**, mit direkter JointSpace-Verbindung über HTTPS/API 6 und Digest-Anmeldung. Keine laufende Python- oder Node-RED-Verbindung erforderlich. Vorhandene Kopplungsdaten können weiterverwendet werden.

## Voraussetzungen und Gerätekompatibilität

**Dieses Modul ist eine Philips-JointSpace-Anbindung und derzeit nicht markenübergreifend.** Die Bezeichnung bleibt deshalb Philips-spezifisch. Android TV oder Google TV allein ist kein Kompatibilitätsnachweis; auch nicht jeder Philips-TV ist abgedeckt.

Benötigt werden IP-Symcon ab 9.0, PHP-cURL, eine erreichbare TV-Adresse und **JointSpace API 6 über HTTPS mit Digest-Anmeldung** (Standardport 1926). Die API-Version und das Kopplungsverfahren müssen zum konkreten TV passen. Ausschließlich HTTP oder andere JointSpace-Versionen unterstützt dieses Modul aktuell nicht.

Einschalten aus Standby ist nur möglich, wenn die TV-API erreichbar bleibt und den Befehl unterstützt. Wake-on-LAN ist nicht enthalten. Lautstärke, aktive Anwendung und Ambilight sind modell- und firmwareabhängig. Eine vollständige Liste freigegebener Modelle liegt noch nicht vor.

**Vor der Installation lesen: [Voraussetzungen, unterstützte Geräte und Grenzen](docs/Voraussetzungen.md).**

## Installation

1. Unter **Modul Control → Hinzufügen** dieses Repository eintragen:

   `https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon`

2. Eine Instanz **Philips TV Steuerung (JointSpace)** erstellen.
3. IP-Adresse eintragen und Änderungen übernehmen. Den Fernseher mit seiner Fernbedienung einschalten.
4. Im Aktionsbereich **Kopplung starten (PIN am TV anzeigen)** drücken, die angezeigte TV-PIN eingeben und **TV-PIN bestätigen und Zugangsdaten speichern** drücken. Das Modul speichert die erzeugten Zugangsdaten und aktiviert die Verbindung automatisch.
5. **Status aktualisieren / Verbindung prüfen** drücken. Alternativ können vorhandene API-Zugangsdaten weiterhin von Hand eingetragen werden.

Das Modul funktioniert unabhängig von der Haussteuerung. Für Anzeige und Bedienung in [Symcon-Hausstatus](https://github.com/Powermaniaxx/Symcon-Hausstatus) dort auf **v0.132 oder neuer** aktualisieren und in der zentralen Hausstatus-Instanz unter **Wohnzimmer → Direkte Philips-TV-Instanz** diese Instanz auswählen.

## Ambilight-Modus auswählen (ab v1.4.1)

Ambilight Ein/Aus funktioniert weiterhin wie bisher. Bei eingeschaltetem und verbundenem TV können in der Instanzkonfiguration mit **Unterstützte Ambilight-Modi einlesen** die vom Gerät gemeldeten Stile abgefragt werden. Anschließend lässt sich ein angezeigter Modus samt angebotener Variante auswählen und mit **Ausgewählten Ambilight-Stil setzen** übertragen. Das Modul verwendet hierfür `/ambilight/supportedstyles` und `/ambilight/currentconfiguration`.

Nur vom TV gemeldete Stile und Varianten können gesendet werden. Nach dem Befehl wird die tatsächliche Einstellung bei der nächsten Statusabfrage kontrolliert. Eine Umschaltung auf Video folgen beim normalen Einschalten wird **nicht** erzwungen; die zuletzt gewählte Einstellung bleibt maßgeblich. Helligkeit, Sättigung und beliebige RGB-Farben sind nicht implementiert, solange die jeweils nötigen gerätespezifischen Einstellungen nicht sicher ermittelt sind.

Die Ambilight-Modi stehen ab Hausstatus v0.138 auch in der Philips-TV-Kachel zur Auswahl. Dort kann die Liste direkt vom TV neu eingelesen werden.

Ab v1.4.2 verwenden **Ambilight-Modus** und **Ambilight-Stilvariante** die Standarddarstellung „Aufzählung“, sobald passende Optionen vorliegen. Eigene Darstellungen bleiben erhalten. Falls eine frühere Modulversion bei diesen Variablen ein benutzerdefiniertes Profil mit dem Präfix `SVPTV.` hinterlegt hat, kannst du über **Auf Standard zurücksetzen** zur neuen Darstellung wechseln.

Alle neuen Git-Commits werden mit der jeweils betroffenen Modulversion im Format `vX.Y.Z: Änderung` beschrieben.

**[Versionshistorie (CHANGELOG)](CHANGELOG.md)** enthält die Änderungen jeder veröffentlichten Repository-Version. Für den Symcon Store muss die Beschreibung der neuesten Version beim Einreichen zusätzlich eingetragen werden.

## Shield und Cinema 40 gemeinsam steuern (ab v1.4.1)

In der Philips-TV-Instanz **Philips automatisch mit Shield ODER AVR steuern** aktivieren und beide Statusvariablen auswählen. Die Shield kann beispielsweise `awake`/`standby` liefern und der AVR einen Boolean für EIN/AUS.

- Wechselt die gemeinsame Quelle von AUS nach EIN, wird einmalig `On` per JointSpace gesendet.
- Wechselt die gemeinsame Quelle von EIN nach AUS, wird einmalig `Standby` gesendet. Anschließend sind automatische Abfragen vollständig ausgesetzt.
- Solange mindestens ein Zuspieler aktiv ist, wird alle 30 Sekunden abgefragt; eine Zustandsänderung startet die nächste Abfrage kurzfristig.
- Beim Übernehmen der Modulkonfiguration werden keine Ein- oder Ausschaltbefehle gesendet. Ein aktiver Zuspieler startet nur das Polling.
- Unbekannte Zustände sind keine verlässliche AUS-Meldung; sie lösen daher kein automatisches Standby aus.
- Die manuelle Statusaktualisierung in der Hausstatus-Kachel und in den Instanzeinstellungen ist weiter möglich, auch wenn beide Zuspieler aus sind.
- Die alte Einzel-Steuersignal-Konfiguration wird in der Oberfläche nicht mehr angeboten. Gespeicherte Einstellungen und deren interne Auswertung bleiben zur Abwärtskompatibilität erhalten, solange der neue ODER-Modus nicht aktiviert ist.

**Wichtig:** Die ältere separate Shield/TV-Kopplung darf nicht parallel TV-Befehle senden. Sie vor Nutzung der neuen automatischen Schaltung deaktivieren. Das Einschalten per JointSpace setzt voraus, dass die TV-API im Standby erreichbar ist. Das Modul kann keine physische Einschaltgarantie geben.

## Funktionen

- TV ein/aus mit ausdrücklichen Befehlen.
- Lautstärke, Stummschaltung und Ambilight ein/aus, soweit vom TV unterstützt.
- Betriebszustand, aktive Anwendung bzw. Eingangs-URI und Ambilight-Modus.
- Fernbedienung über Instanzaktionen und in der Hausstatus-Kachel.
- Unmittelbarer Befehlsversand und getrennte Statusbestätigung.
- Konfigurierbare Abfragen, Standby-Pause und optionales Steuersignal vom AVR, von einer Shield oder einer eigenen Statusvariable.
- TV über dieses Signal auf Wunsch einschalten und/oder ausschalten – beide Optionen unabhängig wählbar und standardmäßig aus.

Nach bestätigtem Standby pausieren Abfragen standardmäßig. Ein Ein-Befehl, manuelles Aktualisieren oder ein passendes Steuersignal startet sie wieder. Wer mit der TV-Fernbedienung einschaltet, kann alternativ Standby-Abfragen aktivieren. Einschalten per API setzt voraus, dass der TV im Standby über das Netzwerk erreichbar bleibt.

Benutzername und Passwort bleiben in der lokalen Symcon-Konfiguration. Die bereits erzeugten API-Zugangsdaten werden benötigt; die TV-PIN dient nicht als Kennwort. Eine bestehende Kopplung bleibt erhalten, solange nicht ausdrücklich eine neue gestartet und vom TV bestätigt wird. Andere Benutzer können ihren eigenen kompatiblen Philips-TV mit JointSpace API 6 über denselben Ablauf koppeln; es sind keine fest hinterlegten Geräte-IDs oder privaten TV-Zugangsdaten erforderlich.

[Einrichtung, Variablen und Skriptbefehle](docs/Einrichtung.md) · [Prüfstand und offene Gerätetests](docs/Pruefung.md) · [Texte für den Module Store](docs/Module-Store.md)

## Diagnose bei Verbindungs- oder Schaltproblemen

Ab v1.0.1 stehen **Verbindungsdiagnose**, **letzte HTTPS-Anfrage** und **letzter Schaltversuch** direkt im Konfigurationsformular. Die Meldung nennt beispielsweise einen Timeout (cURL 28), eine abgewiesene Verbindung (cURL 7), einen TLS-Fehler (cURL 35/60), einen HTTP-Anmeldefehler (401/403) oder eine ungültige TV-Antwort. Zugangsdaten und Antwortinhalte werden dabei nicht ausgegeben.

Nach einem Update die Instanzkonfiguration neu öffnen. Bei laufendem TV **Status aktualisieren / Verbindung prüfen** drücken. Ein eigenständiger Testknopf öffnet auf Wunsch das **Home-Menü** am TV, ohne die Hausstatus-Kachel zu verwenden. Das letzte Schaltergebnis bleibt auch nach erfolgreichen Statusabfragen erhalten. Ein bestätigter HTTP-Empfang ist noch keine bestätigte Ausführung am Gerät.

Neue Instanzen verwenden 5000 ms Zeitlimit pro Anfrage. Bereits gespeicherte Zeitlimits bleiben erhalten; für einen ersten Test lässt sich der Wert unter **Zeitlimit je Anfrage** auf 5000 ms setzen. Die Kopplung muss dafür nicht erneuert werden. Ein Live-Test am betroffenen TV ist weiterhin erforderlich.

## Entwicklung

```sh
php tests/philips-direct.php
php tests/philips-pairing.php
php tests/philips-signal.php
php -n tests/philips-transport.php
python tests/repository-structure.py
```

Der Modultest benötigt die cURL-Erweiterung, verwendet jedoch eine nachgebildete TV-API. Der Transporttest läuft ohne geladene cURL-Erweiterung und bildet deren Funktionen nach. Es werden keine echten TV-Befehle gesendet. Grundlegendes Schalten wurde aus einer realen Installation als funktionierend zurückgemeldet. Die integrierte PIN-Kopplung und weitere Modelle sind noch nicht umfassend live geprüft.

## Shield mit dem Philips koppeln

Das angepasste [Kopplungsskript](docs/Shield-Philips-TV-Kopplung.php) nutzt die Boolean-Aktionsvariable `Power` der in der Hausstatus-Hauptinstanz ausgewählten Philips-TV-Instanz. Nur der Shield-Zustand löst Ein/Standby aus. Ein manueller Start richtet das Ereignis ein und speichert den Ausgangszustand, ohne den TV zu schalten. [Skript einrichten](docs/Shield-Philips-TV-Kopplung.md). Dieser optionale Helfer benötigt Hausstatus; das TV-Modul selbst funktioniert eigenständig.

## Optionales Steuersignal

Das Feld kann leer bleiben. Als Quelle eignet sich ein bestätigter Betriebszustand vom AVR, von einer Shield oder einer eigenen Statusvariable.

- **TV bei aktivem Signal einschalten:** Beim Wechsel von inaktiv auf aktiv wird ein Ein-Befehl gesendet.
- **TV bei inaktivem Signal ausschalten (Standby):** Beim Wechsel von aktiv auf inaktiv wird ein Standby-Befehl gesendet.

Beide Schalter sind zunächst aus und unabhängig wählbar. Ohne diese Optionen setzt ein aktives Signal wie bisher nur die Statusabfragen fort. Das Speichern der Einstellungen löst keinen TV-Befehl aus. Als aktiv gelten `true`, Ganzzahl `1`, `on` oder `awake`; als inaktiv `false`, Ganzzahl `0`, `off` oder `standby`. Unbekannte Zustände unterbrechen die Schaltautomatik, bis wieder ein gültiger Zustandswechsel vorliegt. Gleiche normalisierte Zustände wiederholen keinen Befehl.

Für die Schaltautomatik einen echten Betriebszustand auswählen, keine reine Online-/Erreichbarkeitsmeldung: Deren `false` kann einen Verbindungsfehler bedeuten. Ein paralleles Shield/TV-Kopplungsskript für denselben Ein-/Aus-Ablauf sollte nicht zusätzlich aktiv sein, damit Befehle nicht doppelt gesendet werden.
