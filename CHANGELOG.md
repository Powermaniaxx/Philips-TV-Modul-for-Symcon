# Versionshistorie – Philips TV Steuerung (JointSpace)

Änderungen sind anhand der Commits im Repository dokumentiert. Die Versionsinformationen für den Symcon Module Store werden bei einer Veröffentlichung zusätzlich im Store selbst eingetragen.

## 1.4.1 – 31.10.2026
- Liste der erkannten Ambilight-Modi wird im Status für die Hausstatus-Visualisierung bereitgestellt.
- Die Hausstatus-Kachel kann damit die vom TV zurückgemeldeten Modi auswählen und erneut einlesen (Hausstatus ab 0.138).

## 1.4.0 – 31.10.2026
- Ambilight: Vom TV gemeldete unterstützte Stile und Stilvarianten können über die Instanzkonfiguration eingelesen und ausgewählt werden.
- Neue Aktion zum Umschalten über die JointSpace-API `ambilight/currentconfiguration`. Nicht vom Fernseher gemeldete Varianten werden abgewiesen.
- Ambilight-Stilvariante als zusätzliche Statusvariable; Anzeige erst nach bestätigter TV-Abfrage aktualisiert.
- Vorhandene Ambilight-Ein/Aus-Steuerung bleibt erhalten.
- Die Auswahl erfolgt nur bei eingeschaltetem und erreichbarem TV. Freie Farbwerte, Helligkeit und Sättigung sind noch nicht Bestandteil dieser Version; diese hängen von den API-Funktionen des konkreten Geräts ab.
- Versionshistorie ergänzt.

## 1.3.1 – 31.10.2026
- Alte Einzel-Steuersignal-Einstellungen aus der Instanzkonfiguration entfernt.
- Bestehender Einzel-Steuersignal-Code bleibt intern für vorhandene Installationen erhalten.

## 1.3.0 – 31.10.2026
- Neue automatische TV-Steuerung über zwei frei wählbare Statusvariablen (z. B. Shield und AVR) mit ODER-Verknüpfung.
- Bei mindestens einem aktiven Zuspieler: Einschaltbefehl beim Zustandswechsel und automatische Statusabfrage alle 30 Sekunden.
- Wenn beide Zuspieler inaktiv werden: Standby-Befehl und keine weiteren automatischen Abfragen.
- Bei unbekannten Zuständen kein automatisches Ausschalten; bestehende Statusanzeige wird bei Ende der Überwachung als unbekannt markiert.
- Manuelle Statusaktualisierung bleibt unabhängig von den Zuspielern möglich.
- Zusätzliche Tests für die kombinierte Steuerung angelegt.

## 1.2.0 – 30.10.2026
- Getrennt konfigurierbare Optionen zum Einschalten und Ausschalten des TV über ein einzelnes Steuersignal.
- Schaltbefehle nur bei erkannten gültigen Zustandswechseln, nicht durch bloßes Speichern der Einstellungen.

## 1.1.2 – 30.10.2026
- Bibliotheksname auf „Philips TV Steuerung (JointSpace)“ angepasst.

## 1.1.1 – 30.10.2026
- Kompatibilitätsanforderungen und Grenzen der unterstützten Philips-Modelle genauer dokumentiert.
- Optionales Aktivierungssignal auf AVR-/Shield-/allgemeine Statusvariablen erweitert.

## 1.1.0 – 30.10.2026
- PIN-Kopplung direkt in der Symcon-Instanz ergänzt.
- Optionales Shield-TV-Kopplungsskript angepasst.

## 1.0.1 – 30.10.2026
- Erweiterte Verbindungs- und Befehlsdiagnose im Konfigurationsformular.
- Detailliertere Informationen bei Netzwerk-, TLS- und HTTP-Fehlern.

## 1.0.0 – 30.10.2026
- Erste eigenständige Philips-TV-Anbindung für IP-Symcon 9 über JointSpace API 6 (HTTPS/Digest).
- TV-Ein/Standby, Lautstärke, Stummschaltung, Ambilight Ein/Aus und Statusabfrage.
- Auslesen von aktiver Anwendung und Ambilight-Modus sowie Fernbedienungsbefehle.
- Einstellbare Abfrageintervalle und Standby-Pause.

## Hinweis zum Symcon Module Store
Die Datei dokumentiert die Repository-Versionen. Der Store zeigt bei einer eingereichten Version zusätzlich die vom Entwickler im Einreichungsformular angegebene Versionsinformation an. Die Datei allein veröffentlicht kein Update im Stable-Kanal.
