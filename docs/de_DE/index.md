# Jellyfin Plugin für Jeedom

![Jellyfin Logo](../../plugin_info/jellyfin_icon.png)

Dieses Plugin ermöglicht es Ihnen, Ihren **Jellyfin**-Server mit Jeedom zu verbinden, um den Wiedergabestatus Ihrer verschiedenen Player (Clients) abzurufen, diese zu steuern und durch Ihre Medienbibliothek zu navigieren.

## 🌟 Hauptfunktionen

### 1. Echtzeit-Informationsrückmeldung
* **Automatische Erkennung** aktiver Jellyfin-Clients im Netzwerk.
* **Wiedergabestatus**: Play, Pause, Stop.
* **Medieninformationen**: Titel, Serie, Staffel, Episode, Künstler, Album.
* **Zeit**: Gesamtdauer, aktuelle Position und verbleibende Zeit.
* **Visuell**: Abruf des **Covers** mit automatischer Seitenverhältnisverwaltung (Quadratisch für Musik, Poster für Filme).

### 2. Player-Steuerung (Fernbedienung)
* Play / Pause / Stop.
* Vorheriger / Nächster.
* Positionssteuerung (Seek) über eine interaktive Fortschrittsleiste im Widget.
* *Hinweis: Optimiert für Android TV (Freebox POP, Shield...) mit Latenzmanagement.*

### 3. Bibliotheks-Explorer (Media Center)
Sie müssen Jeedom nicht verlassen, um auszuwählen, was Sie ansehen möchten!
* Klicken Sie auf das Jellyfin-Logo im Widget, um den Explorer zu öffnen.
* **Flüssige Navigation** durch Ihre Ordner, Filme und Musik.
* **Interaktiver Brotkrumenpfad** (Breadcrumb), um einfach in der Hierarchie nach oben zu gelangen.
* **Mediendetails**: Anzeige von Zusammenfassung (Synopsis), Jahr, Community-Bewertung und Dauer.
* **Direktstart**: Starten Sie die Wiedergabe auf dem Zielgerät mit einem einfachen Klick.

### 4. Favoritenverwaltung
Erstellen Sie Verknüpfungen zu Ihren Lieblingsinhalten direkt im Widget.
* **Einfaches Hinzufügen**: Klicken Sie im Explorer auf "Zu Favoriten hinzufügen".
* **Schnellzugriff**: Eine seitliche Schublade im Widget zeigt Ihre Favoriten mit ihren Postern an.
* **One-Click-Start**: Starten Sie Ihre Playlist oder Ihren Lieblingsfilm sofort.

### 5. Technische Optimierungen
* **Python Daemon**: Reaktive und ressourcenschonende WebSocket-Verbindung.
* **Intelligente Filterung**: Saubere Geräteverwaltung zur Vermeidung von Jeedom-Überlastung.
* **Internationalisierung**: Vollständig übersetzte Oberfläche (FR, EN, DE, ES).

---

## 🔧 Installation und Konfiguration

1.  Installieren Sie das Plugin vom Jeedom Market.
2.  Aktivieren Sie das Plugin.
3.  Installieren Sie die **Abhängigkeiten** (erforderlich für den Python-Daemon).
4.  In der Plugin-Konfiguration:
    * Geben Sie die **IP-Adresse** Ihres Jellyfin-Servers ein.
    * Geben Sie den **Port** ein (Standard `8096` oder `443` bei HTTPS).
    * Geben Sie den **API-Schlüssel** ein (In Jellyfin zu generieren: *Dashboard > Erweitert > API-Schlüssel*).
5.  Starten Sie den Daemon (Überprüfen Sie, ob der Status OK ist).
6.  Starten Sie die Wiedergabe auf einem Ihrer Jellyfin-Geräte: Das Gerät wird automatisch in Jeedom erstellt.

---

## 📱 Das Widget

Das Plugin enthält ein spezielles Widget, das perfekt in das Dashboard integriert ist:
* **Dunkles Design** (Dark Mode) im Stil von Jellyfin.
* **Dynamischer Hintergrund** basierend auf dem aktuellen Mediencover (Unschärfeeffekt).
* **Ausziehbare Favoritenschublade** um Platz zu sparen (klicken Sie auf das Herz).
* **Bibliotheks-Taste** (Jellyfin Logo) zum Durchsuchen Ihrer Medien.

---

## ⚠️ FAQ & Hinweise
* **Warum erscheint mein Gerät nicht?**: Starten Sie die Wiedergabe auf dem Gerät. Das Plugin erstellt Geräte erst, wenn sie zum ersten Mal aktiv sind.
* **Steuerung unmöglich?**: Einige Clients (Webbrowser, einige DLNA-TVs) unterstützen keine Fernsteuerung. Das Plugin meldet Informationen, aber die Play/Pause-Tasten sind inaktiv.
* **Leere Bibliothek?**: Überprüfen Sie, ob Ihr Jellyfin-Server eingeschaltet und von Jeedom aus erreichbar ist.

---

**Autor:** NeoRed
**Lizenz:** AGPL