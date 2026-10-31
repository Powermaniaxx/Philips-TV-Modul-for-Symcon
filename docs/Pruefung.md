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

## Ergänzung Philips TV 1.1.1

Das optionale Aktivierungssignal ist allgemein für AVR, Shield oder eigene Statusvariablen beschriftet. Tests decken `true`, Ganzzahl `1`, Groß-/Kleinschreibung und Leerzeichen bei `on`/`awake` sowie inaktive, unbekannte, unveränderte, fremde und nicht konfigurierte Signale ab. Das Signal setzt ausschließlich den Abfragetimer und versendet keinen TV-Einschaltbefehl. Dokumentation enthält Voraussetzungen, fehlende markenübergreifende Unterstützung und einen neutralen Module-Store-Text. Öffentliche Kopplungsvorlagen enthalten keine persönlichen Objekt-IDs; Tests verwenden fiktive Kennungen.

## Ergänzung Philips TV 1.2.0

Zwei unabhängige Signal-Schaltoptionen (beide standardmäßig aus) ergänzt. Neue Fixture prüft Nur-Ein, Nur-Aus, beide und keine Option, Konfigurationsübernahme ohne Schalten, äquivalente Werte ohne Wiederholung, unbekannte und veraltete Zustände, deaktiviertes Modul, laufende PIN-Kopplung sowie fehlgeschlagene Befehle ohne automatische Wiederholung. Vorhandene Tests zu manuellen Befehlen, Transport und PIN-Kopplung bestehen weiterhin. Keine realen TV-Schaltungen während dieser Prüfungen ausgelöst. Automatische Ein-/Aus-Kopplung muss in der Zielinstallation noch live geprüft werden.

Für Signal-Schaltungen wird der aktuelle Quellwert erneut lokal gelesen. Die Prüfung nutzt den tatsächlichen Variablen-Aktualisierungszeitpunkt; Symcons MessageSink-Zähler wird ausschließlich zur Reihenfolgekontrolle verwendet. Tests für kleine gültige Nachrichtenzähler, doppelte/vertauschte Nachrichten und inzwischen überholte Nachrichteninhalte bestanden.

## Ergänzung Philips TV 1.4.1 – offener Live-Prüfstand

Die in 1.3.0 eingeführte ODER-Steuerung verwendet die zwei frei wählbaren Statusquellen Shield und AVR. Beim Aktivieren bzw. Speichern der Konfiguration wird kein TV-Schaltbefehl ausgelöst. Ein Wechsel von beiden AUS auf mindestens einen aktiven Zuspieler erlaubt einen einmaligen Ein-Befehl und die 30-Sekunden-Abfrage; wechseln beide auf AUS, ist Standby vorgesehen und das automatische Polling wird beendet. Ein unbekannter Quellzustand darf keinen Standby-Befehl verursachen. Manuelle Statusabfrage bleibt möglich. Dieses Verhalten ist anhand des Modulcodes nachvollzogen, aber hier nicht an einem echten Gerät bestätigt.

Ab 1.4.0 werden unterstützte Ambilight-Stile samt Varianten vom Fernseher eingelesen und über `ambilight/currentconfiguration` gewählt. Ab 1.4.1 stellt der Modulstatus diese Auswahl der Hausstatus-Kachel ab Version 0.138 bereit. Ob das konkrete Gerät alle angebotenen Varianten zuverlässig umsetzt, ist noch live zu prüfen.

Für den Live-Test: Shield `16350` (`awake`/`standby`) und Cinema 40 `10950` als Statusquellen wählen, vorhandenes separates Shield-TV-Kopplungsskript zuvor deaktivieren und die Zustandsfolge beide AUS → Shield EIN → AVR EIN → Shield AUS → AVR AUS beobachten. Erwartet werden genau ein Ein-Befehl beim ersten Aktivwerden, kein Zwischen-Standby und genau ein Standby beim letzten Ausschalten; nach beiden AUS keine automatischen HTTPS-Abfragen. Anschließend bei eingeschaltetem TV Ambilight-Stile neu einlesen, auswählen, den gemeldeten Ist-Zustand prüfen und die Auswahl aus der Hausstatus-Kachel (mindestens v0.138) wiederholen.

**Noch nicht nachgewiesen:** Dieser Live-Test wurde in diesem Prüfprotokoll nicht durchgeführt; der Ein-Befehl setzt die Erreichbarkeit der JointSpace-API im TV-Standby voraus.
