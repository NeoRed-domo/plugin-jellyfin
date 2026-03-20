# Jellyfin-Plugin für Jeedom

## Beschreibung

Das Jellyfin-Plugin ermöglicht die erweiterte Integration eines Jellyfin-Servers in Jeedom. Es bietet die vollständige Steuerung Ihrer Player, Echtzeit-Informationsrückmeldung (Medien, Cover, Fortschritt) und die Automatisierung von Kinositzungen und kommerziellen Wiedergaben.

### Hauptfunktionen

- **Player-Steuerung**: Play, Pause, Stop, Nächster, Vorheriger, Seek
- **Echtzeit-Informationen**: Titel, Dauer, Position, Cover, Medientyp
- **Bibliothek-Browser**: Durchsuchen und Suchen in Ihrer Jellyfin-Mediathek
- **Favoriten-Verknüpfungen**: Schnellzugriff auf Ihre Lieblingsmedien vom Widget aus
- **Kinositzungen**: Automatisierte Verkettung von Clips nach Abschnitten (Intro, Werbung, Trailer, Film) mit Lichtambiente und Film-Cue-Points
- **Kommerzielle Wiedergaben**: Wiedergabe von Medien-Playlists in Schleife
- **Audio-Normalisierung**: LUFS-Kalibrierung und automatische Verstärker-Lautstärkeregelung
- **Audio-Profile**: Nacht, Kino, THX (Kino) / Stumm, Diskret, Normal, Laut (kommerziell)
- **Mehrsprachig**: Französisch, Englisch, Spanisch, Deutsch

---

## Installation

### Voraussetzungen

- Jeedom Version 4.4 oder höher
- Ein Jellyfin-Server, der über das lokale Netzwerk erreichbar ist
- Ein Jellyfin-API-Schlüssel (generiert im Jellyfin-Dashboard > API-Schlüssel)

### Plugin-Installation

1. Suchen Sie im Jeedom Market nach „Jellyfin" und installieren Sie das Plugin
2. Aktivieren Sie das Plugin
3. Starten Sie die Abhängigkeitsinstallation (Python 3, requests, ffmpeg)
4. Konfigurieren Sie das Plugin (siehe unten)
5. Starten Sie den Daemon

### Server-Konfiguration

Rufen Sie die Plugin-Konfigurationsseite auf:

- **Server-IP**: IP-Adresse Ihres Jellyfin-Servers (ohne `http://`)
- **Server-Port**: Jellyfin-Port (Standard 8096)
- **Jellyfin-API-Schlüssel**: Schlüssel, generiert in Jellyfin > Dashboard > API-Schlüssel

---

## Erkennung von Medientypen

Das Plugin kann den Typ jedes Mediums anhand des Dateipfads identifizieren. Konfigurieren Sie kommagetrennte Schlüsselwörter für jeden Typ:

- **Filme**: z.B. `film, movie`
- **Serien**: z.B. `serie, show`
- **Audio / Musik**: z.B. `music, audio, album`
- **Werbung**: z.B. `pub, advert`
- **Trailer**: z.B. `trailer, bande-annonce`
- **Sound Trailers**: z.B. `jingle, dts, dolby`

---

## Die Jellyfin-Player

### Automatische Erkennung

Player werden automatisch erkannt, wenn sie eine Wiedergabe auf Jellyfin starten. Für jeden erkannten Player wird ein Jeedom-Gerät erstellt.

### Player-Konfiguration

Klicken Sie auf einen Player, um auf seine Konfiguration zuzugreifen:

- **Device ID**: eindeutiger Bezeichner des Players (automatisch erkannt)
- **Rahmen anzeigen**: aktiviert einen farbigen Rahmen um das Widget
- **Rahmenfarbe**: Farbe des Rahmens

#### Audio-Konfiguration (optional)

- **Verstärker-Lautstärkebefehl**: Wählen Sie den Jeedom-Befehl vom Typ Action/Slider, der die Lautstärke Ihres Verstärkers steuert. Erforderlich für die Audio-Normalisierung.
- **Standard-Lautstärke**: Lautstärke, die angewendet wird, wenn kein Clip-spezifisches Volumen definiert ist (0-100)
- **Audio-Ausgangstyp**:
  - *Verstärker (Passthrough)*: Der Verstärker dekodiert das Audio (DTS, AC3). Verwenden Sie diesen Modus, wenn Ihr Player den rohen Audiostrom über HDMI an den Verstärker sendet.
  - *TV / PCM*: Der Client dekodiert das Audio. Verwenden Sie diesen Modus, wenn der Ton direkt aus dem TV kommt.
- **Verstärker-Lautstärke-Infobefehl**: Jeedom-Infobefehl, der die aktuelle Verstärkerlautstärke ausliest. Optional, wird für die Audio-Kalibrierung verwendet.

### Verfügbare Befehle

| Befehl | Typ | Beschreibung |
|--------|-----|--------------|
| Prev | action | Vorheriger Titel (Zurückspulen wenn > 30s) |
| Play | action | Wiedergabe fortsetzen |
| Pause | action | Pausieren |
| Play/Pause | action | Wiedergabe/Pause umschalten |
| Next | action | Nächster Titel |
| Stop | action | Wiedergabe stoppen |
| Title | info | Titel des aktuellen Mediums |
| Status | info | Status (Playing/Paused/Stopped) |
| Duration | info | Gesamtdauer (HH:MM:SS) |
| Position | info | Aktuelle Position |
| Remaining | info | Verbleibende Zeit |
| Cover | info | Medien-Cover (HTML img) |
| Media Type | info | Erkannter Medientyp |
| Set Position | action | Seek zu einer Position (Slider) |
| Profil audio cinéma | info | Aktives Audio-Profil |
| Changer profil cinéma | action | Profil ändern (Nuit/Cinéma/THX/Manuel) |
| Profil audio commercial | info | Aktives kommerzielles Profil |
| Changer profil commercial | action | Kommerzielles Profil ändern |

---

## Das Widget

Das Player-Widget zeigt die Wiedergabeinformationen in Echtzeit an:

- **Cover** des Mediums mit unscharfem Hintergrund
- **Titel**, Status und Medientyp
- **Interaktiver Fortschrittsbalken** (Klick zum Seekn)
- **Steuerung**: Vorheriger, Play/Pause, Stop, Nächster
- **Favoriten-Schaltfläche** (Herz): öffnet das Verknüpfungspanel
- **Sitzungen-Schaltfläche** (Film): öffnet die Liste der verfügbaren Sitzungen
- **Bibliothek-Schaltfläche** (Jellyfin-Logo): öffnet den Browser

### Bibliothek-Browser

Klicken Sie auf das Jellyfin-Logo, um den Browser zu öffnen:

- Ordnernavigation mit Breadcrumb-Pfad
- Suche in der gesamten Bibliothek
- Technische Informationen (Auflösung, Audio-Codec)
- Direkte Wiedergabe oder zu Favoriten hinzufügen

### Favoriten-Verknüpfungen

Das Favoriten-Panel ermöglicht schnellen Zugriff auf Ihre Medien:

- Fügen Sie einen Favoriten über den Browser oder über das Widget hinzu (Herz-Schaltfläche auf dem aktuell wiedergegebenen Medium)
- Klicken Sie auf einen Favoriten, um ihn abzuspielen
- Entfernen Sie einen Favoriten mit der ✕-Schaltfläche

---

## Kinositzungen

### Konzept

Eine Kinositzung ist eine automatisierte Abfolge von Medien, die in Abschnitte gegliedert sind, mit Verwaltung von Lichtambiente und Audio-Lautstärkeregelung.

### Eine Sitzung erstellen

1. Klicken Sie auf **"Nouvelle séance"** auf der Plugin-Seite
2. Wählen Sie **"Séance cinéma"** und vergeben Sie einen Namen
3. Wählen Sie im Reiter **Équipement** den Ziel-Player aus
4. Wechseln Sie zum Reiter **Séance**, um den Inhalt zu konfigurieren

### Die Abschnitte

Eine Kinositzung besteht aus 7 Abschnitten, die jeweils durch eine Farbe gekennzeichnet sind:

| Abschnitt | Farbe | Beschreibung |
|-----------|-------|--------------|
| Préparation | Orange | Aktionen vor der Sitzung (Rollläden schließen, Verstärker einschalten...) |
| Intro | Violett | Einführungsclips (Logos, Jingles) |
| Publicités | Rot | Werbespots |
| Bandes annonces | Cyan | Filmtrailer |
| Court métrage | Gelb | Kurzfilme |
| Trailer audio | Blau | Sound Trailers (DTS, Dolby...) |
| Film | Grün | Der Hauptfilm |

### Die Auslöser (Triggers)

Jeder Abschnitt enthält eine geordnete Liste von Auslösern:

- **Média**: ein Videoclip aus der Jellyfin-Bibliothek
- **Pause**: eine Wartezeit (0 = unbegrenzte Pause, manuelle Fortsetzung)
- **Action**: ein Jeedom-Befehl oder ein Jeedom-Szenario

Auslöser können:
- **Umsortiert** werden mit den Pfeilen ↑ ↓
- **Gelöscht** werden mit der ✕-Schaltfläche
- **Einzeln aktiviert/deaktiviert** werden mit dem Toggle
- **Bearbeitet** werden: Klicken Sie auf das Label einer Pause oder Aktion, um sie zu ändern

### Einen Abschnitt aktivieren/deaktivieren

Jeder Abschnitt verfügt über einen Toggle. Ein deaktivierter Abschnitt wird bei der Wiedergabe übersprungen.

### Film-Cue-Points (Kalibrierung)

Cue-Points ermöglichen es, Lichtambiente zu bestimmten Zeitpunkten des Films auszulösen:

| Cue-Point | Beschreibung |
|-----------|--------------|
| Pré-générique | Der Film beginnt sich dem Ende zu nähern |
| Générique 1 | Beginn des ersten Abspanns |
| Post film 1 | Post-Credits-Szene |
| Générique 2 | Fortsetzung des Abspanns |
| Post film 2 | Zweite Post-Credits-Szene |
| Fin | Ende der Sitzung |

Zum Kalibrieren: Fügen Sie einen Film hinzu, klicken Sie auf „Calibrer tops", verwenden Sie den integrierten Videoplayer, um die Cue-Points zu markieren.

### Lichtambiente

Jeder Abschnitt und jeder Cue-Point kann ein Jeedom-Szenario auslösen. Konfigurieren Sie die Standardwerte in der Plugin-Konfiguration. Jede Sitzung kann diese Werte überschreiben.

Wenn der Zuschauer mit der Fernbedienung pausiert, wird das „Pause"-Ambiente ausgelöst. Bei Fortsetzung der Wiedergabe wird das Ambiente des aktuellen Abschnitts wiederhergestellt.

### Eine Sitzung starten

1. **Vom Editor aus**: Schaltfläche „Lancer"
2. **Vom Widget aus**: 🎬-Schaltfläche
3. **Von einem Szenario aus**: Befehl `start`

---

## Kommerzielle Wiedergaben

### Konzept

Eine kommerzielle Wiedergabe ist eine Medien-Playlist, die in Schleife abgespielt wird, ohne Abschnitte oder Lichtambiente.

### Schleifen-Modi

- **Keine Schleife**: einmalige Wiedergabe
- **Endlosschleife**: startet unbegrenzt neu
- **Schleifenanzahl**: wiederholt N-mal und stoppt dann

---

## Audio-Normalisierung

### Konzept

Die Normalisierung analysiert die Lautstärke jedes Clips (LUFS-Messung) und passt automatisch die Verstärkerlautstärke an, um einen gleichmäßigen Lautstärkepegel zu erreichen. Standard EBU R128 / Netflix / Spotify.

### Kalibrierung

1. **"Calibration audio"** auf der Plugin-Seite
2. Laden Sie das **Rosa-Rauschen**-Referenzsignal herunter und importieren Sie es in Jellyfin (einmalig)
3. Wählen Sie den Player und das Rosa-Rauschen aus
4. Stellen Sie Ihren Verstärker auf die ideale Lautstärke ein und geben Sie den Wert ein
5. Analysieren Sie den LUFS-Wert und speichern Sie

### Eine Sitzung normalisieren

1. Schaltfläche **"Normaliser le son"** im Editor
2. Wählen Sie Schnellanalyse oder vollständige Analyse
3. Die automatischen Lautstärken werden berechnet und angewendet

### Audio-Profile

| Kino-Profil | Offset | Kommerzielles Profil | Offset |
|-------------|--------|----------------------|--------|
| Nuit | -20 dB | Muet | vol=0 |
| Cinéma | 0 dB | Discret | -20 dB |
| THX | +10 dB | Normal | 0 dB |
| Manuel | Bypass | Fort | +5 dB |
| | | Manuel | Bypass |

Das Profil „Manuel" deaktiviert die Lautstärkeregelung durch das Plugin vollständig.

---

## Integration in Jeedom-Szenarien

### Verfügbare Befehle

```
#[Salon][Séance Samedi][start]#     → Sitzung starten
#[Salon][Séance Samedi][stop]#      → Stoppen
#[Salon][Séance Samedi][state]#     → Status (stopped/playing/paused)
#[Salon][Séance Samedi][progress]#  → Fortschritt (%)

#[Salon][Shield TV][set_audio_profile]# → Kino-Profil ändern
#[Salon][Shield TV][set_commercial_audio_profile]# → Kommerzielles Profil ändern
```

---

## Fehlerbehebung

### Der Daemon startet nicht
Überprüfen Sie die Konfiguration (IP, Port, API-Schlüssel) und die Abhängigkeiten.

### Die Clips werden nicht verkettet
Überprüfen Sie die `jellyfin`-Logs im INFO-Modus. Der Daemon muss gestartet sein.

### Die Normalisierung funktioniert nicht
ffmpeg muss installiert sein. Die Kalibrierung muss durchgeführt worden sein. Der Lautstärkebefehl muss auf dem Player konfiguriert sein.

### Die Lautstärke ist zu laut / zu leise
Passen Sie die Offsets pro Abschnitt, die Rosa-Rauschen-Kompensation (+4 dB Standard) an, oder verwenden Sie das Profil „Manuel", um die manuelle Kontrolle zurückzuerlangen.
