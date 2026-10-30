# Texte für die Einreichung im Symcon Module Store

## Name

Philips TV Steuerung (JointSpace)

## Bundle-ID

Vorschlag: `io.github.powermaniaxx.philipstv`.

Die Verfügbarkeit muss bei der Einreichung geprüft werden. Die gewählte Bundle-ID bleibt bei späteren Versionen gleich. Sie ist eine Store-Kennung und ersetzt nicht die vorhandenen Bibliotheks- und Modul-GUIDs.

## Kurzbeschreibung

Lokale Steuerung kompatibler Philips-Fernseher über JointSpace API 6 mit PIN-Kopplung direkt in Symcon.

## Beschreibung

Die Bibliothek verbindet IP-Symcon direkt mit kompatiblen Philips-Fernsehern. Die PIN-Kopplung kann in der Instanz gestartet und bestätigt werden; die dabei erzeugten Zugangsdaten werden lokal gespeichert. Vorhandene API-Zugangsdaten lassen sich ebenfalls verwenden.

Zur Verfügung stehen Ein-/Standby-Befehle, Lautstärke, Stummschaltung, Fernbedienungsbefehle und modellabhängig Ambilight sowie Informationen zur aktiven Anwendung. Statusabfragen und Schaltbefehle werden getrennt verarbeitet. Bei bestätigtem Standby können Abfragen pausieren. Optional kann eine AVR-, Shield- oder eigene Betriebszustandsvariable die Abfragen wieder starten. Zwei getrennte, zunächst deaktivierte Optionen erlauben zusätzlich, den TV bei aktivem Signal einzuschalten und/oder bei inaktivem Signal in Standby zu schalten. Das Übernehmen der Einstellungen schaltet den TV nicht.

Vorausgesetzt werden IP-Symcon ab 9.0, PHP-cURL sowie eine vom Symcon-System erreichbare JointSpace-6-Schnittstelle über HTTPS mit Digest-Anmeldung. Standardport ist 1926. Es handelt sich um eine Philips-Anbindung, nicht um eine allgemeine Android-/Google-TV- oder markenübergreifende TV-Steuerung. Einschalten aus Standby setzt eine weiterhin erreichbare und dafür geeignete TV-API voraus; Wake-on-LAN ist nicht enthalten. Einzelne Funktionen hängen von Modell und Firmware ab.

Die Bibliothek arbeitet eigenständig. Eine Anzeige und Bedienung über Symcon-Hausstatus ist optional möglich. Home Assistant, Node-RED und Python werden für die direkte TV-Verbindung nicht benötigt.

## Dokumentationslink

https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon#readme

Ausführliche [Voraussetzungen und Grenzen](Voraussetzungen.md) sowie [Einrichtung und Diagnose](Einrichtung.md).

## Prüfstand

Automatische Prüfungen für Transport, Befehle, PIN-Kopplung und Statusverarbeitung sind vorhanden. Eine umfassende Freigabe aller TV-Modelle und Firmwarestände liegt nicht vor. Die integrierte PIN-Kopplung und weitere Geräte müssen noch live geprüft werden.

## Einreichung

Die Einreichung erfolgt durch den Herausgeber im Entwicklerbereich des Symcon-Kontos. Dort werden unter anderem GIT-URL, Bundle-ID, Kanal und Beschreibung gepflegt. Stable-Einreichungen durchlaufen laut Symcon eine Prüfung; Testing und Beta stehen als weitere Kanäle zur Verfügung. Diese Datei enthält die vorbereiteten Texte, keine Bestätigung einer erfolgten Store-Einreichung oder Freigabe.

Quelle: [Symcon – Einreichen](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/store/einreichen/).
