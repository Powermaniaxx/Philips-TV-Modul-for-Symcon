# Prüfstand Philips TV – Hausstatus 0.132 / Philips TV 1.0.0

Ausgangsstand: Powermaniaxx/Symcon-Hausstatus `ba86d39f23cf4258fc2bae45a4e3bfb8b26467ce` (0.131, Build 132). Neue Fassungen: Hausstatus 0.132, Build 133, und separate Philips-TV-Bibliothek 1.0.0, Build 1. Beide werden in ihren eigenen Repositories veröffentlicht. Noch nicht in einer realen Symcon-Installation bzw. am TV geprüft.

## Bestanden

- PHP-Syntax aller Moduldateien und Symcon-Verzeichnisstruktur mit einem Modul je Repository.
- `philips-direct.php` (separates Philips-Repository): Konfiguration, echte Befehlsplanung mit nachgebildeter TV-API, keine Statusrunde vor POST, getrennte Bestätigung, Lautstärkeskala mit Minimum ungleich 0, schnell aufeinanderfolgende Audio-Befehle, Werteprüfung, Authentifizierungsfehler, Teilfunktionen, nicht unterstützte Endpunkte, Standby-Pause und Wake-Signal, veraltete Daten, Vorrang von Befehlen gegenüber laufenden Abfragen.
- `philips-house.php`: tatsächlicher Hausstatus-RequestAction-Einstieg, zentrale Konfigurationsquelle und abgeleitete Kacheln, unveränderte Befehlstypen, direkter TV-Status, State-Abonnement, alte Philips-Flow-Kommandos unterdrückt, ungültige Instanz und Deaktivierung.
- `philips-transport.php` (separates Philips-Repository): tatsächlicher Transportcode mit nachgebildeten cURL-Funktionen; API-6-HTTPS-URL, Digest, getrennte Zugangsdatenoptionen, Zertifikatsprüfung, IPv6, Zeitlimits, keine Weiterleitungen, HTTP- und JSON-Fehler, leere/HTML-Bestätigung, keine Kennwörter aus TV-Fehlerantworten in Diagnose.
- `philips-direct.js`: gesamte JavaScript-Syntax, richtige Ziele aller neuen Kachelaktionen, Lautstärkeregler, aufklappbare Fernbedienung, Bedienzustände bei deaktiviertem/unerreichbarem/veraltetem TV, Textausgabe statt Ausführen von TV-Inhalten.
- Headless-Chrome mit echter Kachel-CSS und den echten Renderer-/Bedienfunktionen: 320 und 390 Pixel Hochformat, 844 Pixel Querformat, Desktop; kein horizontaler Überlauf, mindestens 40 Pixel hohe neue Touch-Tasten, funktionierende Ereignisse, Erhalt des Fernbedienungs-Klappzustands.
- Bestehende Prüfungen für Heizungszentrale, Heizprofil, Startseitenverwaltung (PHP), Befehlsantwort/Entprellung (PHP), Anwesenheitswoche, Räume/Terrasse, Mediengeräte und Schlafzimmer, Shield/TV-Kopplung, Variablensichtbarkeit, Unterseitenversionen, Visualisierungsspiegelung, Waschmaschinen-Zähler/Verlauf und Wasserbett bestanden. Ebenso Cast-, Philips-Altweg-, Shield-Layout-/Power-/Parser- und Farbsteuerungstests.

## Bereits im unveränderten Ausgangsstand fehlgeschlagen

Die folgenden Fehler wurden gegen separat gespeicherte Originaldateien des obigen Commits reproduziert. Diese Philips-Änderung bearbeitet die betroffenen Funktionen nicht:

- `laundry.php`: Erwartung „Heating pause must still show running“.
- `home-environment-layout.js`: widersprüchliche Boolean-Erwartung in Zeile 44.
- `homepage-parts.js`: fehlendes DOM-Element in der isolierten Testumgebung.
- `interaction-performance.js`: fehlende Variable `roomSensorRefreshPending` in der isolierten Testumgebung.
- `philips-standby.js`: Speichern des lokalen Shield-Teststatus liefert false in dieser Windows-Testumgebung.

`shield-rpc.js` konnte seinen lokalen Testserver wegen eines vom Ausführungsumfeld abgewiesenen Zugriffs auf 127.0.0.1 nicht erreichen. Die betroffene RPC-Implementierung ist unverändert.

## Noch am Gerät prüfen

Mit den vorhandenen Kopplungsdaten einmal Status sowie Ein/Aus, Lautstärke, Stumm, Ambilight und Fernbedienung testen. Erreichbarkeit nach längerem Standby prüfen. Angezeigte App-/Eingangsdaten und die vom konkreten Modell angebotenen Teilfunktionen bestätigen. Der Transporttest prüft Request-Optionen und Fehlerbehandlung, keine reale TLS-Verbindung zum Fernseher.

## Ergänzung Philips TV 1.0.1

Syntax, Repository-Struktur, SDK-Fixture und nachgebildeter cURL-Transport erneut geprüft. Neue Fälle unterscheiden cURL 6, 7, 28, 35, 60, 52, 56 und unbekannte Codes; HTTP-Anmeldefehler und HTML statt JSON bleiben getrennt. Fehlertexte und Debug-Metadaten enthalten weder Fixture-Benutzernamen noch Fixture-Passwort. Test für dauerhaft gespeicherten Schaltfehler trotz nachfolgend erfolgreicher Statusabfrage sowie für Diagnoseanzeige beim Wiederöffnen und Aktualisieren des Formulars bestanden. HTTP-Empfang wird nicht als beobachtete Ausführung ausgegeben. Keine Befehle an einen echten TV gesendet; konkreter Nutzerfehler noch nicht reproduziert.

## Ergänzung Philips TV 1.1.0

PIN-Kopplung mit nachgebildetem TV: erste Anfrage ohne bestehende Anmeldedaten, read/write/control-Berechtigungen, Digest-Grant mit neuem Geräteschlüssel, HMAC über Zeitstempel und PIN einschließlich führender Null, Speicherung erst nach bestätigtem Grant, Schutz bestehender Zugangsdaten bei Fehlern, Ablauf und Adresswechsel, Wiederaufnahme der Abfragen, Synchronisation maskierter Formularfelder und Ausschluss von PIN/Schlüsseln aus Debug-/Statusausgaben geprüft. Kopplung am echten TV noch offen.

Shield-Skript gegen den vom Nutzer gelieferten Stand angepasst und isoliert geprüft: neue Power-Aktionsvariable, ausgewählte TV-Instanz, manueller Start ohne Schalten, gleicher Zustand ohne Wiederholungen, Netzwerk-/Zeitstempelfehler ohne Schalten, unveränderter Cache bei fehlgeschlagenem Aktionsaufruf und Wiederverwendung vorhandener Ereignis-/Cache-IDs.
