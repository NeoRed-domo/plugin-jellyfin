# Änderungsprotokoll

Diese Datei listet alle bemerkenswerten Änderungen am Jellyfin-Plugin auf.

## [1.2.1] - 20-03-2026

🔧 **Fehlerbehebungen**

* **Echtzeit-Widget**: Titel und Medientyp werden jetzt bei Clip-Wechseln in Echtzeit aktualisiert (fehlende Listener)
* **Echtzeit-Cover**: Base64-Speicherung (zu groß für Jeedom-Events) durch eine leichtgewichtige Proxy-URL ersetzt — das Cover wird nun sofort aktualisiert
* **Monitoring-Halo**: Der grüne Halo auf dem aktiven Clip funktioniert jetzt bei kommerziellen Wiedergaben und Kinositzungen
* **Race Condition Double-Fire**: Motor-Aktionen (Start, Lautstärke, Beleuchtung) werden nicht mehr doppelt ausgelöst, dank frühzeitigem Cache-Schreiben vor langsamen HTTP-Aufrufen

---

## [1.2.0] - 20-03-2026

🌟 **Audio-Normalisierung & Wichtige Verbesserungen**

### Audio-Normalisierung (LUFS)
* **Kalibrierung**: integriertes Rosa-Rauschen (-24 LUFS), Messung über ffmpeg, EBU R128-Formel
* **Kino-Profile**: Nacht (-20 dB), Kino (0 dB), THX (+10 dB), Manuell (Bypass)
* **Kommerzielle Profile**: Stumm, Diskret (-20 dB), Normal (0 dB), Laut (+5 dB), Manuell (Bypass)
* **Verstärkersteuerung**: Lautstärke automatisch pro Clip mit Offsets pro Abschnitt angepasst
* **Audio-Ausgangstyp**: Verstärker (Passthrough, AC3-DRC-Korrektur) oder TV/PCM
* **Echtzeit-Profilwechsel** während der Wiedergabe

### Sitzungsverbesserungen
* **Live-Monitoring**: animierter grüner Halo auf dem aktuell wiedergegebenen Abschnitt und Clip
* **Fortschritt**: basiert auf der tatsächlichen Wiedergabedauer (nicht auf der Clip-Anzahl)
* **Technische Badges**: Videoauflösung und Audio-Codec werden bei jedem Clip angezeigt
* **Toggles**: individuelle Aktivierung/Deaktivierung von Abschnitten und Auslösern
* **Schleifenzähler** sichtbar bei kommerziellen Wiedergaben

### Dokumentation & Übersetzungen
* **Vollständige Dokumentation** in 4 Sprachen (FR, EN, ES, DE)
* **305 übersetzte Zeichenketten** pro Sprache

### Wichtige Fehlerbehebungen
* Zuverlässige Verkettung (Playlist PlayNow + Jellyfin Auto-Advance)
* Genaue LUFS-Messung (temporäre Datei statt Pipe, AC3-DRC-Korrektur)
* Double-Fire bei Auto-Advance (sofortiges Cache-Schreiben)
* Verstärker-Lautstärkebefehl speicherte den Namen statt der ID

---

## [1.1.0] - 14-03-2026

🎬 **Wiedergabe-Sitzungen**

### Kinositzungen
* **7 Abschnitte**: Vorbereitung, Intro, Werbung, Trailer, Kurzfilm, Sound Trailer, Film
* **Lichtambiente**: Jeedom-Szenario pro Abschnitt und pro Film-Cue-Point
* **Kalibrierbare Film-Cue-Points**: Vor-Abspann, Abspann 1, Post-Film 1, Abspann 2, Post-Film 2, Ende
* **Sitzungseditor**: Akkordeon-Oberfläche mit farbcodierten Abschnitten, Drag & Drop von Auslösern

### Kommerzielle Wiedergaben
* **Playlist in Schleife**: endlos, N-mal oder einmalige Wiedergabe
* **Automatische Verkettung** über Jellyfin-Playlist

### Widget
* **Sitzungsschaltfläche** (🎬) zum Starten einer Sitzung vom Dashboard aus
* **Sitzungsliste** mit Poster, Dauer und Statistiken

### Ausführungsmotor
* Daemon-Polling bei 0,25s für maximale Reaktionsfähigkeit
* Zustandsmaschine (warten auf Start, Wiedergabe, Medium beendet)
* Erkennung von Jellyfin Auto-Advance (Resync)
* Vorwärmung des nächsten Clips (Transcode-Pre-Caching)
* HTTPS/HTTP Video-Proxy für Kalibrierung

---

## [1.0.0] - 15-02-2026

🌍 **Erste stabile Version**

* **Mehrsprachig**: Plugin übersetzt in Englisch (en_US), Deutsch (de_DE) und Spanisch (es_ES)
* **Fix**: Widget-Bibliotheksschaltfläche
* **Fix**: PHP-Syntax auf der Konfigurationsseite
* **Dokumentation**: Links und Struktur für den Market

---

## [Beta] - 14-02-2026

🌟 **Mediathek & Favoriten**

* **Bibliothek-Browser**: Navigation, Suche, Mediendetails
* **Favoritenverwaltung**: Hinzufügen, Schnellstart, Löschen
* **Verbesserte Fortschrittsleiste**
* **Intelligente Filterung** nicht steuerbarer Clients

---

## [Beta] - 12-02-2026

🎉 **Erstveröffentlichung**

* Automatische Player-Erkennung
* Mediensteuerung: Play, Pause, Stop, Seek
* Echtzeit-Metadaten und Cover
* Dashboard-Widget mit interaktiver Fortschrittsleiste
* Python-Daemon für permanente Verbindung
