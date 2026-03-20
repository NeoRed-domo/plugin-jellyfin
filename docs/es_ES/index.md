# Plugin Jellyfin para Jeedom

## Descripción

El plugin Jellyfin permite la integración avanzada de un servidor Jellyfin en Jeedom. Ofrece el control completo de sus reproductores, la recepción de información en tiempo real (medios, carátulas, progresión) y la automatización de sesiones de cine y emisiones comerciales.

### Funcionalidades principales

- **Control de reproductores**: play, pause, stop, siguiente, anterior, seek
- **Información en tiempo real**: título, duración, posición, carátula, tipo de medio
- **Explorador de biblioteca**: navegar y buscar en su mediateca Jellyfin
- **Atajos favoritos**: acceso rápido a sus medios favoritos desde el widget
- **Sesiones de cine**: encadenamiento automatizado de clips por secciones (intro, anuncios, tráilers, película) con ambientes de iluminación y puntos de referencia de película
- **Emisiones comerciales**: reproducción en bucle de listas de medios
- **Normalización de audio**: calibración LUFS y control automático del volumen del amplificador
- **Perfiles de audio**: Noche, Cine, THX (cine) / Silencio, Discreto, Normal, Alto (comercial)
- **Multi-idioma**: francés, inglés, español, alemán

---

## Instalación

### Requisitos previos

- Jeedom versión 4.4 o superior
- Un servidor Jellyfin accesible desde la red local
- Una clave API de Jellyfin (generada en el Dashboard de Jellyfin > Claves de API)

### Instalación del plugin

1. Desde el Market de Jeedom, busque "Jellyfin" e instale el plugin
2. Active el plugin
3. Lance la instalación de dependencias (Python 3, requests, ffmpeg)
4. Configure el plugin (ver más abajo)
5. Inicie el daemon

### Configuración del servidor

Acceda a la página de configuración del plugin:

- **IP del servidor**: dirección IP de su servidor Jellyfin (sin `http://`)
- **Puerto del servidor**: puerto de Jellyfin (por defecto 8096)
- **Clave API de Jellyfin**: clave generada en Jellyfin > Dashboard > Claves de API

---

## Detección de tipos de medio

El plugin puede identificar el tipo de cada medio analizando la ruta del archivo. Configure palabras clave separadas por comas para cada tipo:

- **Películas**: ej. `film, movie`
- **Series**: ej. `serie, show`
- **Audio / Música**: ej. `music, audio, album`
- **Publicidad**: ej. `pub, advert`
- **Tráilers**: ej. `trailer, bande-annonce`
- **Sound Trailers**: ej. `jingle, dts, dolby`

---

## Los Reproductores Jellyfin

### Detección automática

Los reproductores se detectan automáticamente cuando inician una reproducción en Jellyfin. Se crea un dispositivo Jeedom para cada reproductor detectado.

### Configuración de un reproductor

Haga clic en un reproductor para acceder a su configuración:

- **Device ID**: identificador único del reproductor (detectado automáticamente)
- **Mostrar borde**: activa un marco de color alrededor del widget
- **Color del borde**: color del marco

#### Configuración de audio (opcional)

- **Comando de volumen del amplificador**: seleccione el comando Jeedom de tipo action/slider que controla el volumen de su amplificador. Necesario para la normalización de audio.
- **Volumen por defecto**: volumen aplicado cuando no se define ningún volumen por clip (0-100)
- **Tipo de salida de audio**:
  - *Amplificador (passthrough)*: el amplificador decodifica el audio (DTS, AC3). Use este modo si su reproductor envía el flujo de audio sin procesar al amplificador por HDMI.
  - *TV / PCM*: el cliente decodifica el audio. Use este modo si el sonido sale directamente de la TV.
- **Comando info volumen del amplificador**: comando Jeedom de tipo info que lee el volumen actual del amplificador. Opcional, utilizado para la calibración de audio.

### Comandos disponibles

| Comando | Tipo | Descripción |
|---------|------|-------------|
| Prev | action | Pista anterior (rebobinar si > 30s) |
| Play | action | Reanudar la reproducción |
| Pause | action | Poner en pausa |
| Play/Pause | action | Alternar reproducción/pausa |
| Next | action | Pista siguiente |
| Stop | action | Detener la reproducción |
| Title | info | Título del medio en reproducción |
| Status | info | Estado (Playing/Paused/Stopped) |
| Duration | info | Duración total (HH:MM:SS) |
| Position | info | Posición actual |
| Remaining | info | Tiempo restante |
| Cover | info | Carátula del medio (HTML img) |
| Media Type | info | Tipo de medio detectado |
| Set Position | action | Seek a una posición (slider) |
| Profil audio cinéma | info | Perfil de audio activo |
| Changer profil cinéma | action | Cambiar el perfil (Nuit/Cinéma/THX/Manuel) |
| Profil audio commercial | info | Perfil comercial activo |
| Changer profil commercial | action | Cambiar el perfil comercial |

---

## El Widget

El widget del reproductor muestra en tiempo real la información de reproducción:

- **Carátula** del medio con fondo difuminado
- **Título**, estado y tipo de medio
- **Barra de progreso** interactiva (clic para seek)
- **Controles**: anterior, play/pause, stop, siguiente
- **Botón favoritos** (corazón): abre el panel de atajos
- **Botón sesiones** (película): abre la lista de sesiones disponibles
- **Botón biblioteca** (logo Jellyfin): abre el explorador

### Explorador de biblioteca

Haga clic en el logo de Jellyfin para abrir el explorador:

- Navegación por carpetas con ruta de navegación (breadcrumb)
- Búsqueda en toda la biblioteca
- Información técnica (resolución, códec de audio)
- Reproducción directa o añadir a favoritos

### Atajos favoritos

El panel de favoritos permite un acceso rápido a sus medios:

- Añada un favorito desde el explorador o desde el widget (botón corazón sobre el medio en reproducción)
- Haga clic en un favorito para reproducirlo
- Elimine un favorito con el botón ✕

---

## Sesiones de Cine

### Concepto

Una sesión de cine es una secuencia automatizada de medios organizados en secciones, con gestión de ambientes de iluminación y control del volumen de audio.

### Crear una sesión

1. Haga clic en **"Nouvelle séance"** en la página del plugin
2. Elija **"Séance cinéma"** y asigne un nombre
3. En la pestaña **Équipement**, seleccione el reproductor destino
4. Pase a la pestaña **Séance** para configurar el contenido

### Las secciones

Una sesión de cine se compone de 7 secciones, cada una identificada por un color:

| Sección | Color | Descripción |
|---------|-------|-------------|
| Préparation | Naranja | Acciones antes de la sesión (cerrar persianas, encender amplificador...) |
| Intro | Violeta | Clips de introducción (logos, jingles) |
| Publicités | Rojo | Anuncios publicitarios |
| Bandes annonces | Cian | Tráilers de películas |
| Court métrage | Amarillo | Cortometrajes |
| Trailer audio | Azul | Sound trailers (DTS, Dolby...) |
| Film | Verde | La película principal |

### Los disparadores (triggers)

Cada sección contiene una lista ordenada de disparadores:

- **Média**: un clip de vídeo de la biblioteca Jellyfin
- **Pause**: un tiempo de espera (0 = pausa ilimitada, reanudación manual)
- **Action**: un comando Jeedom o un escenario Jeedom

Los disparadores pueden ser:
- **Reordenados** con las flechas ↑ ↓
- **Eliminados** con el botón ✕
- **Activados/Desactivados** individualmente con el toggle
- **Editados**: haga clic en la etiqueta de una pausa o acción para modificarla

### Activar/Desactivar una sección

Cada sección dispone de un toggle. Una sección desactivada se ignora durante la reproducción.

### Puntos de referencia de película (calibración)

Los puntos de referencia permiten activar ambientes de iluminación en momentos precisos de la película:

| Punto de referencia | Descripción |
|---------------------|-------------|
| Pré-générique | La película empieza a desacelerar |
| Générique 1 | Inicio de los primeros créditos |
| Post film 1 | Escena post-créditos |
| Générique 2 | Reanudación de los créditos |
| Post film 2 | Segunda escena post-créditos |
| Fin | Fin de la sesión |

Para calibrar: añada una película, haga clic en "Calibrer tops", use el reproductor de vídeo integrado para marcar los puntos de referencia.

### Ambientes de iluminación

Cada sección y cada punto de referencia puede activar un escenario Jeedom. Configure los valores por defecto en la configuración del plugin. Cada sesión puede sobrescribir estos valores.

Si el espectador pone en pausa con el mando a distancia, se activa el ambiente "Pause". Al reanudar, se restaura el ambiente de la sección en curso.

### Iniciar una sesión

1. **Desde el editor**: botón "Lancer"
2. **Desde el widget**: botón 🎬
3. **Desde un escenario**: comando `start`

---

## Emisiones Comerciales

### Concepto

Una emisión comercial es una lista de medios reproducida en bucle, sin secciones ni ambientes de iluminación.

### Modos de bucle

- **Sin bucle**: reproducción única
- **Bucle infinito**: se reinicia indefinidamente
- **Número de bucles**: repite N veces y luego se detiene

---

## Normalización de Audio

### Concepto

La normalización analiza el volumen de cada clip (medición LUFS) y ajusta automáticamente el volumen del amplificador para un nivel sonoro homogéneo. Estándar EBU R128 / Netflix / Spotify.

### Calibración

1. **"Calibration audio"** en la página del plugin
2. Descargue e importe el **ruido rosa** de referencia en Jellyfin (una sola vez)
3. Seleccione el reproductor y el ruido rosa
4. Ajuste su amplificador al volumen ideal e introduzca el valor
5. Analice el LUFS y guarde

### Normalizar una sesión

1. Botón **"Normaliser le son"** en el editor
2. Elija análisis rápido o completo
3. Los volúmenes automáticos se calculan y aplican

### Perfiles de audio

| Perfil cine | Offset | Perfil comercial | Offset |
|-------------|--------|-------------------|--------|
| Nuit | -20 dB | Muet | vol=0 |
| Cinéma | 0 dB | Discret | -20 dB |
| THX | +10 dB | Normal | 0 dB |
| Manuel | bypass | Fort | +5 dB |
| | | Manuel | bypass |

El perfil "Manuel" desactiva completamente el control del volumen por parte del plugin.

---

## Integración con Escenarios Jeedom

### Comandos disponibles

```
#[Salon][Séance Samedi][start]#     → Iniciar la sesión
#[Salon][Séance Samedi][stop]#      → Detener
#[Salon][Séance Samedi][state]#     → Estado (stopped/playing/paused)
#[Salon][Séance Samedi][progress]#  → Progreso (%)

#[Salon][Shield TV][set_audio_profile]# → Cambiar perfil de cine
#[Salon][Shield TV][set_commercial_audio_profile]# → Cambiar perfil comercial
```

---

## Solución de problemas

### El daemon no arranca
Verifique la configuración (IP, puerto, clave API) y las dependencias.

### Los clips no se encadenan
Verifique los logs `jellyfin` en modo INFO. El daemon debe estar iniciado.

### La normalización no funciona
ffmpeg debe estar instalado. La calibración debe estar realizada. El comando de volumen debe estar configurado en el reproductor.

### El volumen es demasiado alto / demasiado bajo
Ajuste los offsets por sección, la compensación de ruido rosa (+4 dB por defecto), o use el perfil "Manuel" para retomar el control manual.
