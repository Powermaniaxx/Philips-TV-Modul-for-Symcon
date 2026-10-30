# Voraussetzungen und unterstützte Geräte

## Geltungsbereich

Dieses Modul stellt eine Philips-JointSpace-Anbindung bereit. **Es ist kein markenübergreifendes Android-TV-Modul.** Der Name „Philips TV Steuerung (JointSpace)“ benennt deshalb Marke, Zweck und Schnittstelle. Samsung, LG, Sony und andere Marken werden durch diese Bibliothek derzeit nicht unterstützt. Ein beliebiges Gerät mit Android TV oder Google TV ist dadurch noch nicht kompatibel.

Entscheidend ist die tatsächlich angebotene TV-Schnittstelle: **JointSpace API 6 über HTTPS mit Digest-Anmeldung**. Der Modulcode verwendet fest den Pfad `/6/` und das Philips-PIN-Kopplungsverfahren. Ein anderer Port macht daraus weder eine andere API-Version noch eine unverschlüsselte HTTP-Verbindung.

## Technische Voraussetzungen

| Voraussetzung | Bedeutung |
| --- | --- |
| IP-Symcon ab 9.0 | Dies ist die Mindestversion der Bibliothek. |
| PHP-cURL in Symcon verfügbar | Wird für die HTTPS-Verbindung benötigt. |
| Passende TV-Schnittstelle | JointSpace API 6, HTTPS und Digest müssen vom konkreten Gerät angeboten werden. |
| Erreichbare TV-Adresse | Symcon muss die konfigurierte IP-Adresse bzw. den Hostnamen und HTTPS-Port erreichen können. Standardport ist 1926. Netztrennung, Gastnetz oder Firewall dürfen die Verbindung nicht blockieren. |
| Erfolgreiche Kopplung | Neue PIN-Kopplung im Modul oder gültige vorhandene API-Zugangsdaten. Die PIN selbst ist nicht das dauerhaft gespeicherte Kennwort. |
| Eingeschalteter TV beim ersten Test | Der Fernseher muss den PIN-Dialog anzeigen und auf die Statusabfrage antworten können. |
| Unterstützte Einzelfunktionen | Lautstärke, Ambilight und aktive Anwendung hängen von den auf dem Modell vorhandenen Endpunkten ab. |

Das TV-Modul benötigt weder Home Assistant noch Python, Node-RED, AVR, Shield oder Hausstatus. Die Hausstatus-Kachel und das gesonderte Shield-Kopplungsskript sind optionale Ergänzungen. Ein Internet-/Cloudkonto ist im Modul nicht vorgesehen; die Kommunikation erfolgt lokal zum TV.

## Welche Fernseher sind abgedeckt?

| Gerät bzw. Schnittstelle | Einordnung für dieses Modul |
| --- | --- |
| Philips mit JointSpace 6, HTTPS und Digest | Vorgesehene Schnittstelle; Funktionen am konkreten Modell prüfen. |
| Philips mit ausschließlich JointSpace 1 oder 5 | Im aktuellen Modul nicht implementiert. |
| Philips mit ausschließlich HTTP, etwa auf Port 1925 | Im aktuellen Modul nicht implementiert. |
| Philips mit Saphi, Titan OS oder anderer Firmware | Keine pauschale Zusage. Nur die passende Schnittstelle genügt als Ausgangspunkt; einzelne Befehle müssen am Gerät funktionieren. |
| Beliebiger Android-/Google-TV oder NVIDIA Shield | Keine direkte TV-Anbindung über dieses Modul zugesagt. Das Betriebssystem ersetzt JointSpace nicht. |
| Fernseher anderer Marken | Derzeit keine Markenanbindung implementiert oder freigegeben. |

Auch der Markenname Philips allein garantiert die Funktion nicht. Es gibt keine vollständig geprüfte Modell- und Firmwareliste für diese Bibliothek. Die Dokumentation anderer JointSpace-Anbindungen beschreibt ebenfalls unterschiedliche API-Versionen, Transporte und modellabhängige Funktionen; deren gesamte Geräteliste ist keine Freigabeliste dieses Moduls. [Hintergrund zu JointSpace-Modellunterschieden](https://www.home-assistant.io/integrations/philips_js/).

## Ein- und Ausschalten, Standby und Tiefschlaf

Ein-/Standby-Befehle werden ausdrücklich über die TV-API gesendet. **Einschalten funktioniert nur, solange die TV-API im Standby erreichbar bleibt und der Fernseher den Einschaltbefehl unterstützt.** Die am TV dafür angebotenen Netzwerkeinstellungen unterscheiden sich nach Modell und Firmware.

Ein ausgeschalteter Netzwerkadapter, Tiefschlaf oder ein vom Strom getrenntes Gerät kann keine HTTPS-Anfrage entgegennehmen. Dieses Modul enthält aktuell keinen Wake-on-LAN- oder Infrarot-Ersatz. Dass Status oder Ausschalten funktionieren, beweist daher nicht automatisch, dass Einschalten nach längerem Standby funktioniert. [Hinweise zum Einschalten über JointSpace](https://www.home-assistant.io/integrations/philips_js/#turn-on-device).

Standardmäßig pausieren die Statusabfragen nach bestätigtem Standby vollständig. Ein Einschaltbefehl aus dem Modul, manuelles Aktualisieren oder ein optionales Aktivierungssignal setzt sie fort. Wer ausschließlich mit der TV-Fernbedienung einschaltet und kein solches Signal verwendet, muss manuell aktualisieren oder ein Standby-Abfrageintervall einstellen.

## Optionales Aktivierungssignal

Die Auswahl darf leer bleiben. Als Quelle eignet sich beispielsweise der Betriebszustand eines AVR, einer Shield oder eine eigene Statusvariable in Symcon. Ein **Wertwechsel auf Boolean `true`, Ganzzahl `1` oder die Zeichenkette `on` bzw. `awake`** setzt die Abfragen fort. Bei diesen Zeichenketten werden Groß-/Kleinschreibung und umgebende Leerzeichen ignoriert.

Das Signal startet nur die Statusabfrage. Es sendet keinen TV-Einschaltbefehl, aktiviert kein deaktiviertes Modul und ersetzt keine erreichbare TV-Schnittstelle. Andere Zahlen, unbekannte Zustände und inaktive Werte lösen keine Wiederaufnahme aus. Für Geräte mit anderen Statuscodierungen kann eine eigene Boolean-Variable das gewünschte Signal abbilden.

## Modellabhängige Grenzen

- Ambilight setzt einen passenden TV mit zugänglicher Ambilight-API voraus. Das Modul schaltet Ambilight ein/aus; der angezeigte Modus ist nicht automatisch ein auswählbarer Modus.
- Die Audio-Steuerung braucht gültige Minimum-, Maximum-, Lautstärke- und Stummwerte. Ein über HDMI-CEC gesteuerter AVR kann sich anders verhalten als der interne TV-Ton.
- Eine aktive Anwendung kann als technischer Paketname erscheinen. Die Anzeige eines Eingangs ist nur möglich, wenn die TV-Antwort diese Information enthält.
- Die Fernbedienung bietet das Eingangsmenü; eine allgemeine automatische HDMI-Direktumschaltung wird nicht eingerichtet.
- Eine erfolgreiche HTTP-Antwort bestätigt den Empfang der Anfrage, nicht zwingend die sichtbare Ausführung jedes Befehls.

## Eigene Installation prüfen

TV einschalten, Adresse speichern und die Kopplung durchführen oder vorhandene API-Zugangsdaten verwenden. Danach **Status aktualisieren / Verbindung prüfen** und bei Bedarf **Home-Menü am eingeschalteten TV testen** ausführen. Anschließend Lautstärke, Stumm, unterstützte Ambilight-Funktionen und Ein-/Standby getrennt testen, einschließlich Einschalten nach längerem Standby.

Automatische Tests bilden Symcon und TV-Antworten nach. Sie bestätigen die geprüften Programmabläufe, ersetzen aber keinen Gerätetest. Die neue integrierte PIN-Kopplung sowie weitere Modelle sind noch nicht umfassend live geprüft. Diagnose und letzter Schaltversuch sind im Konfigurationsformular sichtbar; Zugangsdaten gehören nicht in öffentliche Fehlerberichte.
