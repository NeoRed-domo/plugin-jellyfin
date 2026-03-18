/*
 * Gestion du Plugin Jellyfin
 * Auteur : NeoRed
 */

// Fonction de traduction sécurisée
function _t(str) {
    if (typeof jeedom !== 'undefined' && jeedom.ui && jeedom.ui.translate) {
        return jeedom.ui.translate(str);
    }
    return str; // Fallback au français si le traducteur est absent
}

// On écoute le nouveau nom d'action 'add_jellyfin'
$('body').off('click', '.eqLogicAction[data-action=add_jellyfin]').on('click', '.eqLogicAction[data-action=add_jellyfin]', function (event) {
    event.preventDefault();
    event.stopPropagation();

    bootbox.prompt(_t('Nom de l\'équipement ?'), function (result) {
        if (result !== null && result !== '') {
            $.ajax({
                type: 'POST',
                url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                data: {
                    action: 'add',
                    name: result // C'est ça que le PHP va récupérer
                },
                dataType: 'json',
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                },
                success: function (data) {
                    if (data.state != 'ok') {
                        $('#div_alert').showAlert({
                            message: data.result,
                            level: 'danger'
                        });
                        return;
                    }
                    $('#div_alert').showAlert({
                        message: _t('Équipement ajouté avec succès'),
                        level: 'success'
                    });
                    
                    // CORRECTION REDIRECTION : On force l'URL complète
                    // Cela t'emmène direct sur la page de config de l'ID créé
                    window.location.href = 'index.php?v=d&m=jellyfin&p=jellyfin&id=' + data.result.id;
                }
            });
        }
    });
});

$('.eqLogicAction[data-action=gotoPluginConf]').on('click', function () {
    window.location.href = 'index.php?v=d&p=plugin&id=jellyfin';
});


// --- EXPLORATEUR DE BIBLIOTHÈQUE ---

var _savedState = {
    id: null,
    name: "",
    path: [],
    item: null
};

if (typeof JellyfinBrowser !== 'undefined') {
    _savedState.id = JellyfinBrowser.currentEqLogicId;
    _savedState.name = JellyfinBrowser.currentEqLogicName;
    _savedState.path = JellyfinBrowser.currentPath; 
    _savedState.item = JellyfinBrowser.selectedItem; 
}

var JellyfinBrowser = {
    currentEqLogicId: _savedState.id, 
    currentEqLogicName: _savedState.name,
    currentPath: _savedState.path || [], 
    selectedItem: _savedState.item,

    ticksToTime: function(ticks) {
        if (!ticks) return "";
        var totalSeconds = Math.floor(ticks / 10000000);
        var hours = Math.floor(totalSeconds / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;
        
        var res = "";
        if (hours > 0) res += hours + "h ";
        if (minutes > 0 || hours > 0) res += (minutes < 10 && hours > 0 ? "0" + minutes : minutes) + "m ";
        res += (seconds < 10 ? "0" + seconds : seconds) + "s";
        return res;
    },

    open: function (eqLogicId, eqLogicName) {
        JellyfinBrowser.currentEqLogicId = eqLogicId;
        JellyfinBrowser.currentEqLogicName = eqLogicName || ""; 
        JellyfinBrowser.currentPath = [];
        JellyfinBrowser.selectedItem = null;

        var titleStr = "<span style='color:#fff;'><i class='fas fa-film'></i> " + _t("Bibliothèque Jellyfin");
        if(JellyfinBrowser.currentEqLogicName !== "") {
            titleStr += " <span style='color:#888; margin:0 5px;'>|</span> <span style='color:#1DB954; font-weight:bold;'>" + JellyfinBrowser.currentEqLogicName + "</span>";
        }
        titleStr += "</span>";

        var myModal = bootbox.dialog({
            title: titleStr,
            message: `
                <div id="jellyfin-browser-container" style="height: 70vh; display: flex; flex-direction: column;">
                    <div id="jellyfin-top-bar" style="padding: 10px; background: #333; border-bottom: 1px solid #444; color: #fff; font-size: 14px; display: flex; align-items: center; justify-content: space-between;">
                        <div id="jellyfin-breadcrumbs" style="flex-grow: 1; margin-right: 10px;">
                            <span class="cursor hover-text" onclick="JellyfinBrowser.loadFolder('')"><i class="fas fa-home"></i> ${_t("Accueil")}</span>
                        </div>
                        <div class="input-group" style="width: 200px;">
                             <input type="text" id="jellyfin-search-input" class="form-control input-sm" placeholder="${_t("Rechercher...")}" style="background: #222; border: 1px solid #444; color: #fff;">
                             <span class="input-group-btn">
                                <button class="btn btn-default btn-sm" type="button" onclick="JellyfinBrowser.search()" style="background: #444; border: 1px solid #444; color: #fff;"><i class="fas fa-search"></i></button>
                             </span>
                        </div>
                    </div>

                    <div id="jellyfin-browser-content" style="flex-grow: 1; overflow-y: auto; padding: 20px; background: #202020; display: flex; flex-wrap: wrap; align-content: flex-start;">
                        <div style="width: 100%; text-align: center; margin-top: 50px; color: #ccc;"><i class="fas fa-spinner fa-spin fa-3x"></i><br>${_t("Chargement...")}</div>
                    </div>
                    <div id="jellyfin-selection-info" style="display:none; padding: 15px; background: #2b2b2b; border-top: 1px solid #444; color: #fff; min-height: 100px;">
                         <div style="display: flex;">
                             <div id="sel-img-container" style="width: 60px; height: 90px; margin-right: 15px; flex-shrink: 0; background: #000; display:none; border-radius:4px; overflow:hidden;">
                                <img id="sel-img" src="" style="width:100%; height:100%; object-fit: cover;">
                             </div>
                             <div style="flex-grow: 1;">
                                 <div style="font-size: 18px; font-weight:bold; color: #1DB954; margin-bottom: 5px;">
                                    <span id="sel-title">${_t("Aucun")}</span>
                                 </div>
                                 <div style="font-size: 13px; color: #aaa; margin-bottom: 8px;">
                                    <span id="sel-year"></span> 
                                    <span id="sel-duration" style="margin-left:10px; color:#bbb; background:#444; padding:1px 5px; border-radius:3px;"></span>
                                    <span id="sel-rating" style="margin-left:10px; color:#f39c12;"></span>
                                    <span id="sel-tech" style="margin-left:15px; font-family: sans-serif; font-size: 11px;"></span>
                                 </div>
                                 <div id="sel-overview" style="font-size: 13px; color: #ccc; line-height: 1.4; max-height: 60px; overflow-y: auto;"></div>
                             </div>
                         </div>
                    </div>
                </div>
                <style>
                    .jellyfin-modal-fullscreen .modal-dialog { width: 90% !important; max-width: 90% !important; margin: 30px auto; }
                    .jellyfin-modal-fullscreen .modal-content { background-color: #202020; border: 1px solid #444; box-shadow: 0 0 20px rgba(0,0,0,0.8); }
                    .jellyfin-modal-fullscreen .modal-header { border-bottom: 1px solid #444; background-color: #1a1a1a; color: white; }
                    .jellyfin-modal-fullscreen .close { color: white; opacity: 0.8; }
                    .jellyfin-modal-fullscreen .modal-footer { border-top: 1px solid #444; background-color: #1a1a1a; }
                    .jelly-card { width: 140px; margin: 10px; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; position: relative; }
                    .jelly-card:hover { transform: scale(1.05); z-index: 10; }
                    .jelly-card.selected .jelly-img-container { border: 3px solid #1DB954; box-shadow: 0 0 15px rgba(29, 185, 84, 0.6); }
                    .jelly-img-container { width: 130px; height: 195px; background: #333; border-radius: 6px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.5); position: relative; display: flex; align-items: center; justify-content: center; border: 3px solid transparent; }
                    .jelly-img-container img { width: 100%; height: 100%; object-fit: cover; }
                    .jelly-folder .jelly-img-container { height: 130px; background: #444; }
                    .jelly-title { margin-top: 8px; text-align: center; font-size: 12px; line-height: 1.3; max-height: 32px; overflow: hidden; width: 100%; color: #ddd; text-shadow: 1px 1px 2px black; }
                    .hover-text:hover { color: #1DB954; text-decoration: underline; }
                    #sel-overview::-webkit-scrollbar { width: 4px; }
                    #sel-overview::-webkit-scrollbar-thumb { background: #555; border-radius: 2px; }
                    .btn-favorite { background-color: #c23642 !important; border-color: #c23642 !important; color: white !important; }
                    .btn-favorite:hover { background-color: #d64552 !important; border-color: #d64552 !important; }
                </style>
            `,
            buttons: {
                cancel: { 
                    label: _t("Annuler"), 
                    className: "btn-default pull-left", 
                    callback: function () {} 
                },
                createCmd: {
                    label: "<i class='fas fa-heart'></i> " + _t("Ajouter aux favoris"), 
                    className: "btn-favorite", 
                    callback: function (e) {
                        if (JellyfinBrowser.selectedItem) {
                            var item = JellyfinBrowser.selectedItem;
                            JellyfinBrowser.createCommand(item.Id, item.Name, item.ImgTag, e.target);
                        } else {
                            bootbox.alert(_t("Veuillez sélectionner un média."));
                        }
                        return false; 
                    }
                },
                playNow: {
                    label: "<i class='fas fa-play'></i> " + _t("Lire maintenant"),
                    className: "btn-success",
                    callback: function () {
                        if (JellyfinBrowser.selectedItem) {
                            JellyfinBrowser.playItem(JellyfinBrowser.selectedItem.Id, 'play_now');
                        } else {
                            bootbox.alert(_t("Veuillez sélectionner un média."));
                            return false; 
                        }
                    }
                },
                validate: {
                    label: "OK",
                    className: "btn-primary",
                    callback: function () {}
                }
            },
            className: 'jellyfin-modal-fullscreen'
        });
        
        // GESTION DU CLAVIER (Touche Entrée)
        $('#jellyfin-search-input').on('keypress', function (e) {
            if (e.which === 13) {
                JellyfinBrowser.search();
            }
        });

        $('#jellyfin-selection-info').hide();
        JellyfinBrowser.loadFolder('');
    },

    goBackTo: function(index) {
        var target = JellyfinBrowser.currentPath[index];
        JellyfinBrowser.currentPath = JellyfinBrowser.currentPath.slice(0, index);
        JellyfinBrowser.loadFolder(target.id, target.name);
    },
    
    // NOUVELLE FONCTION DE RECHERCHE
    search: function() {
        var searchTerm = $('#jellyfin-search-input').val();
        if(searchTerm === "") {
             JellyfinBrowser.loadFolder(''); // Si vide, on recharge l'accueil
             return;
        }

        $('#jellyfin-browser-content').html('<div style="width: 100%; text-align: center; margin-top: 50px; color: #ccc;"><i class="fas fa-spinner fa-spin fa-3x"></i><br>' + _t("Recherche...") + '</div>');
        $('#jellyfin-selection-info').hide();
        JellyfinBrowser.selectedItem = null;
        
        // Mise à jour du fil d'ariane pour indiquer la recherche
        var bcHtml = '<span class="cursor hover-text" onclick="JellyfinBrowser.loadFolder(\'\')"><i class="fas fa-home"></i> ' + _t("Accueil") + '</span>';
        bcHtml += ' <span style="color:#888;">&gt;</span> <span class="label label-primary" style="background:#1DB954;">Recherche : ' + searchTerm + '</span>';
        $('#jellyfin-breadcrumbs').html(bcHtml);

        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'getLibrary', search: searchTerm }, // On envoie 'search'
            dataType: 'json',
            success: function (data) {
                if (data.state != 'ok') {
                    $('#jellyfin-browser-content').html('<div class="alert alert-danger">' + data.result + '</div>');
                    return;
                }
                JellyfinBrowser.renderItems(data.result);
            }
        });
    },

    loadFolder: function (parentId, parentName) {
        // Vider le champ de recherche quand on navigue
        $('#jellyfin-search-input').val(''); 

        $('#jellyfin-browser-content').html('<div style="width: 100%; text-align: center; margin-top: 50px; color: #ccc;"><i class="fas fa-spinner fa-spin fa-3x"></i><br>' + _t("Chargement...") + '</div>');
        $('#jellyfin-selection-info').hide();
        JellyfinBrowser.selectedItem = null;

        if (parentId === '') { 
            JellyfinBrowser.currentPath = []; 
        } else if (parentName) { 
            JellyfinBrowser.currentPath.push({id: parentId, name: parentName}); 
        }
        
        var bcHtml = '<span class="cursor hover-text" onclick="JellyfinBrowser.loadFolder(\'\')"><i class="fas fa-home"></i> ' + _t("Accueil") + '</span>';
        
        $.each(JellyfinBrowser.currentPath, function(idx, item){
             bcHtml += ' <span style="color:#888;">&gt;</span> ';
             if (idx === JellyfinBrowser.currentPath.length - 1) {
                 bcHtml += '<span class="label label-default" style="background:#555;">' + item.name + '</span>';
             } else {
                 bcHtml += '<span class="label label-default cursor hover-text" onclick="JellyfinBrowser.goBackTo(' + idx + ')">' + item.name + '</span>';
             }
        });
        $('#jellyfin-breadcrumbs').html(bcHtml);

        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'getLibrary', parentId: parentId },
            dataType: 'json',
            success: function (data) {
                if (data.state != 'ok') {
                    $('#jellyfin-browser-content').html('<div class="alert alert-danger">' + data.result + '</div>');
                    return;
                }
                JellyfinBrowser.renderItems(data.result);
            }
        });
    },

    renderItems: function (result) {
        var container = $('#jellyfin-browser-content');
        container.empty();

        if (!result || !result.Items || result.Items.length === 0) {
            container.html(`
                <div style="width:100%; text-align:center; padding:50px; color: #777; font-size: 1.2em;">
                    <i class="fas fa-network-wired" style="font-size: 30px; margin-bottom: 10px; opacity: 0.5;"></i><br>
                    ${_t("Aucun résultat")}<br>
                    <span style="font-size: 0.8em; color: #666;">(${_t("Ou serveur Jellyfin hors ligne")})</span>
                </div>
            `);
            return;
        }

        $.each(result.Items, function (index, item) {
            var isFolder = item._is_folder; 
            var title = item.Name;
            var imageUrl = item._full_img_url; 
            var imgTag = item._img_tag; 
            var overview = (item.Overview) ? item.Overview.replace(/'/g, "\\'").replace(/"/g, '&quot;') : '';
            var year = (item.ProductionYear) ? item.ProductionYear : '';
            var rating = (item.CommunityRating) ? item.CommunityRating : '';
            var duration = (item.RunTimeTicks) ? JellyfinBrowser.ticksToTime(item.RunTimeTicks) : '';
            
            // NOUVEAU : Récupération des infos techniques
            var videoRes = (item._video_res) ? item._video_res : '';
            var audioInfo = (item._audio_info) ? item._audio_info : '';

            var cardHtml = '';
            if (isFolder) {
                var imgContent = (item.ImageTags && item.ImageTags.Primary) ? `<img src="${imageUrl}" loading="lazy">` : `<i class="fas fa-folder" style="font-size: 50px; color: #aaa;"></i>`;
                cardHtml = `<div class="jelly-card jelly-folder" onclick="JellyfinBrowser.loadFolder('${item.Id}', '${title.replace(/'/g, "\\'")}')"><div class="jelly-img-container">${imgContent}</div><div class="jelly-title">${title}</div></div>`;
            } else {
                var imgContent = `<img src="${imageUrl}" loading="lazy" onerror="this.onerror=null;this.parentNode.innerHTML='<i class=\\'fas fa-film\\' style=\\'font-size:40px;color:#555;\\'></i>';">`;
                // MODIF : Passage des arguments videoRes et audioInfo
                var runTimeTicks = item.RunTimeTicks || 0;
                cardHtml = `<div class="jelly-card jelly-media" id="card-${item.Id}" onclick="JellyfinBrowser.selectMedia('${item.Id}', '${title.replace(/'/g, "\\'")}', '${imgTag}', '${year}', '${rating}', '${overview}', '${imageUrl}', '${duration}', '${videoRes}', '${audioInfo}', ${runTimeTicks})"><div class="jelly-img-container">${imgContent}</div><div class="jelly-title">${title}</div></div>`;
            }
            container.append(cardHtml);
        });
    },

    // MODIF : Ajout des arguments videoRes et audioInfo
    selectMedia: function (itemId, title, imgTag, year, rating, overview, imgUrl, duration, videoRes, audioInfo, runTimeTicks) {
        $('.jelly-card').removeClass('selected');
        $('#card-' + itemId).addClass('selected');
        JellyfinBrowser.selectedItem = {Id: itemId, Name: title, ImgTag: imgTag, RunTimeTicks: runTimeTicks || 0, VideoRes: videoRes || '', AudioInfo: audioInfo || ''};
        
        $('#sel-title').text(title);
        $('#sel-year').text(year ? year : '');
        
        if (duration) {
            $('#sel-duration').html('<i class="far fa-clock"></i> ' + duration).show();
        } else {
            $('#sel-duration').hide();
        }

        if (rating) { $('#sel-rating').html('<i class="fas fa-star"></i> ' + rating); } 
        else { $('#sel-rating').html(''); }

        // --- NOUVEAU : GESTION DES BADGES TECH ---
        var techHtml = '';
        var badgeStyle = 'background: #333; color: #ddd; padding: 2px 6px; border-radius: 4px; border: 1px solid #555; margin-right: 6px; letter-spacing: 0.5px;';
        
        if (videoRes && videoRes !== '') {
            var colorRes = '#ddd';
            if(videoRes === '4K') colorRes = '#1DB954'; // Vert Jellyfin pour la 4K
            techHtml += '<span style="' + badgeStyle + ' color:'+colorRes+';">' + videoRes + '</span>';
        }
        if (audioInfo && audioInfo !== '') {
            techHtml += '<span style="' + badgeStyle + '">' + audioInfo + '</span>';
        }
        $('#sel-tech').html(techHtml);
        // -----------------------------------------

        $('#sel-overview').text(overview ? overview : _t("Pas de résumé disponible."));
        if (imgUrl) { $('#sel-img').attr('src', imgUrl); $('#sel-img-container').show(); } 
        else { $('#sel-img-container').hide(); }

        $('#jellyfin-selection-info').fadeIn(200);
    },

    playItem: function (itemId, mode) {
        var btn = $('.btn-success');
        var originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> ' + _t("Envoi..."));
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'play_media', id: JellyfinBrowser.currentEqLogicId, mediaId: itemId, mode: mode },
            dataType: 'json',
            success: function (data) {
                if (data.state != 'ok') { bootbox.alert(_t("Erreur : ") + data.result); btn.html(originalText); } 
                else { bootbox.hideAll(); }
            }
        });
    },

    createCommand: function (itemId, name, imgTag, btnElement) {
        var btn = (btnElement) ? $(btnElement).closest('.btn') : $('.jellyfin-modal-fullscreen .modal-footer .btn-info');
        var originalText = "<i class='fas fa-heart'></i> " + _t("Ajouter aux favoris"); 
        btn.html('<i class="fas fa-spinner fa-spin"></i> ' + _t("Ajout..."));
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'create_command', id: JellyfinBrowser.currentEqLogicId, mediaId: itemId, name: name, imgTag: imgTag },
            dataType: 'json',
            success: function (data) {
                btn.html(originalText);
                if (data.state != 'ok') {
                    if(data.result && data.result.indexOf("existe déjà") !== -1) {
                         var notif = $('<div style="position:fixed; top:20px; right:20px; background:#f39c12; color:white; padding:15px; border-radius:5px; z-index:9999; box-shadow: 0 4px 12px rgba(0,0,0,0.5);">' + _t("Déjà dans les favoris") + '</div>');
                         $('body').append(notif);
                         setTimeout(function(){ notif.fadeOut(500, function(){ $(this).remove(); }); }, 2000);
                    } else { bootbox.alert(_t("Erreur : ") + data.result); }
                } else {
                    var notif = $('<div style="position:fixed; top:20px; right:20px; background:#1DB954; color:white; padding:15px; border-radius:5px; z-index:9999; box-shadow: 0 4px 12px rgba(0,0,0,0.5); font-weight:bold;"><i class="fas fa-check"></i> ' + _t("Favori ajouté !") + '</div>');
                    $('body').append(notif);
                    setTimeout(function(){ notif.fadeOut(500, function(){ $(this).remove(); }); }, 2000);
                }
            }
        });
    }
};

// --- AUTO-REFRESH : Signature + Détection ID Supérieur (Avec Sécurité) ---
var _lastDbSignature = null;

setInterval(function() {
    // Sécurité : On ne lance la vérif que si la liste des équipements est visible
    if ($('.eqLogicThumbnailContainer').is(':visible')) {
        
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'all' },
            dataType: 'json',
            global: false, // Pas de spinner
            error: function(request, status, error) {},
            success: function(data) {
                if (data.state == 'ok') {
                    // Liste des IDs visibles en base
                    var dbVisibleIds = data.result
                        .filter(function(item) { return item.isVisible == 1; })
                        .map(function(item) { return parseInt(item.id); }) // On force en entier
                        .sort(function(a, b) { return a - b; }); // Tri numérique

                    // 1. GESTION CLASSIQUE (Signature)
                    // Détecte les ajouts/suppressions "normaux"
                    var currentSignature = JSON.stringify(dbVisibleIds);

                    if (_lastDbSignature === null) {
                        _lastDbSignature = currentSignature;
                        
                        // --- 2. GESTION "RACE CONDITION" (Premier passage uniquement) ---
                        // Cas : L'équipement a été recréé AVANT que le script ne démarre.
                        
                        // On trouve l'ID le plus grand affiché sur la page
                        var maxDomId = 0;
                        $('.eqLogicDisplayCard[data-eqLogic_id]').each(function() {
                            var thisId = parseInt($(this).attr('data-eqLogic_id'));
                            if (thisId > maxDomId) maxDomId = thisId;
                        });

                        // On cherche si la base contient un ID plus récent (plus grand)
                        var potentialNewId = -1;
                        for (var i = 0; i < dbVisibleIds.length; i++) {
                            if (dbVisibleIds[i] > maxDomId) {
                                potentialNewId = dbVisibleIds[i];
                                break; // On a trouvé un candidat
                            }
                        }

                        if (potentialNewId !== -1) {
                            // C'est louche ! Un ID en base est plus récent que ce qu'on affiche.
                            
                            // SECURITE ANTI-BOUCLE : Est-ce qu'on a déjà refresh pour cet ID ?
                            var lastRefreshedId = sessionStorage.getItem('jellyfin_last_autorefresh_id');
                            
                            if (lastRefreshedId != potentialNewId) {
                                // C'est un VRAI nouveau (ou recréé). On refresh !
                                if (typeof jeedom !== 'undefined' && jeedom.ui) {
                                     $('#div_alert').showAlert({message: _t('Équipement récent détecté, finalisation...'), level: 'success'});
                                }
                                
                                // On marque le coup pour ne pas boucler au prochain chargement
                                sessionStorage.setItem('jellyfin_last_autorefresh_id', potentialNewId);
                                
                                setTimeout(function(){ window.location.reload(); }, 1000);
                            } else {
                                // C'est un "Faux Client" (Fantôme) pour lequel on a déjà refresh. On l'ignore.
                                // console.log("Refresh ignoré pour le fantôme ID : " + potentialNewId);
                            }
                        }
                        return;
                    }

                    // Suite de la gestion classique (Passages suivants)
                    if (_lastDbSignature !== currentSignature) {
                        if (typeof jeedom !== 'undefined' && jeedom.ui) {
                             $('#div_alert').showAlert({message: _t('Liste des équipements modifiée, rafraîchissement...'), level: 'success'});
                        }
                        _lastDbSignature = currentSignature;
                        setTimeout(function(){ window.location.reload(); }, 1000);
                    }
                }
            }
        });
    }
}, 5000);

// --- GESTION DES SÉANCES ---

$('body').off('click', '.eqLogicAction[data-action=add_session]').on('click', '.eqLogicAction[data-action=add_session]', function() {
    bootbox.dialog({
        title: _t('Nouvelle séance'),
        message: '<div class="form-group">' +
            '<label>' + _t('Nom de la séance') + '</label>' +
            '<input type="text" id="input_session_name" class="form-control" placeholder="' + _t('Ex: Soirée Interstellar') + '">' +
            '</div>' +
            '<div class="form-group">' +
            '<label>' + _t('Type') + '</label>' +
            '<select id="input_session_type" class="form-control">' +
            '<option value="cinema">' + _t('Séance cinéma') + '</option>' +
            '<option value="commercial">' + _t('Diffusion commerciale') + '</option>' +
            '</select>' +
            '</div>',
        buttons: {
            cancel: { label: _t('Annuler'), className: 'btn-default' },
            confirm: {
                label: _t('Créer'), className: 'btn-success',
                callback: function() {
                    var name = $('#input_session_name').val();
                    var type = $('#input_session_type').val();
                    if (!name) return false;
                    $.ajax({
                        type: 'POST',
                        url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                        data: { action: 'create_session', name: name, session_type: type },
                        dataType: 'json',
                        success: function(data) {
                            if (data.state == 'ok') {
                                window.location.href = 'index.php?v=d&m=jellyfin&p=jellyfin&id=' + data.result.id;
                            } else {
                                $('#div_alert').showAlert({ message: data.result, level: 'danger' });
                            }
                        }
                    });
                }
            }
        }
    });
});

// Sélecteur lecteur → maj session_data.player_id
$('#sel_session_player').on('change', function() {
    if (typeof SessionEditor !== 'undefined' && SessionEditor.sessionData) {
        SessionEditor.sessionData.player_id = parseInt($(this).val()) || null;
        SessionEditor.save();
    }
});

// --- ÉDITEUR DE SÉANCE (ACCORDÉON) ---

var SessionEditor = {
    eqLogicId: null,
    sessionType: null,
    sessionData: null,
    sectionsMeta: null,
    marksMeta: null,
    _openSections: {},

    load: function(eqLogicId) {
        SessionEditor.eqLogicId = eqLogicId;
        $('#session-editor-container').html('<div class="text-center" style="padding:40px;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'get_session_data', id: eqLogicId },
            dataType: 'json',
            success: function(data) {
                if (data.state != 'ok') {
                    $('#session-editor-container').html('<div class="alert alert-danger">' + data.result + '</div>');
                    return;
                }
                SessionEditor.sessionType = data.result.session_type;
                SessionEditor.sessionData = data.result.session_data;
                SessionEditor.sectionsMeta = data.result.sections_meta;
                SessionEditor.marksMeta = data.result.marks_meta;
                if (SessionEditor.sessionType == 'cinema') {
                    SessionEditor.renderCinema();
                } else {
                    SessionEditor.renderCommercial();
                }
            }
        });
    },

    renderCinema: function() {
        var html = '';
        var order = SessionEditor.sectionsMeta.order;
        var labels = SessionEditor.sectionsMeta.labels;
        var colors = SessionEditor.sectionsMeta.colors;

        // Couleurs bien séparées sur la roue chromatique (~51° d'écart)
        var pastelBg = {
            'preparation':   'rgba(255,170,50,0.10)',   // orange chaud (30°)
            'intro':         'rgba(180,70,220,0.10)',    // violet (280°)
            'pubs':          'rgba(220,50,50,0.10)',     // rouge (0°)
            'trailers':      'rgba(50,180,220,0.10)',    // cyan (190°)
            'short_film':    'rgba(220,200,50,0.10)',    // jaune (50°)
            'audio_trailer': 'rgba(50,120,220,0.10)',    // bleu (220°)
            'film':          'rgba(50,200,100,0.10)'     // vert (140°)
        };
        var pastelBorder = {
            'preparation':   'rgba(255,170,50,0.35)',
            'intro':         'rgba(180,70,220,0.35)',
            'pubs':          'rgba(220,50,50,0.35)',
            'trailers':      'rgba(50,180,220,0.35)',
            'short_film':    'rgba(220,200,50,0.35)',
            'audio_trailer': 'rgba(50,120,220,0.35)',
            'film':          'rgba(50,200,100,0.35)'
        };

        // === BARRE D'OUTILS EN HAUT ===
        html += '<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#1a1a1a; border:1px solid #333; border-radius:4px; margin-bottom:12px;">';
        html += '  <div style="display:flex; align-items:center; gap:10px;">';
        html += '    <span style="color:#888; font-size:11px; text-transform:uppercase;"><i class="fas fa-headphones"></i> ' + _t('Profil') + '</span>';
        html += '    <select id="session-audio-profile" style="width:auto; font-size:12px; padding:3px 8px; height:28px; background:#333; color:#fff; border:1px solid #555; border-radius:3px;" onchange="SessionEditor.setAudioProfile(this.value)">';
        html += '      <option value="cinema">' + _t('Cinéma') + '</option>';
        html += '      <option value="night">' + _t('Nuit') + '</option>';
        html += '      <option value="thx">THX</option>';
        html += '      <option value="manual">' + _t('Manuel') + '</option>';
        html += '    </select>';
        html += '  </div>';
        html += '  <button class="btn btn-xs btn-default" onclick="SessionEditor.collapseAll()"><i class="fas fa-compress-alt"></i> ' + _t('Tout replier') + '</button>';
        html += '</div>';

        // === SECTIONS ===
        for (var i = 0; i < order.length; i++) {
            var key = order[i];
            var section = SessionEditor.sessionData.sections[key] || { triggers: [] };
            var triggers = section.triggers || [];
            var color = colors[key] || '#888';
            var label = labels[key] || key;
            var dur = SessionEditor.calculateDuration(triggers);
            var count = triggers.length;
            var isOpen = SessionEditor._openSections[key] || false;
            var bg = pastelBg[key] || 'transparent';
            var border = pastelBorder[key] || '#333';

            var sectionEnabled = (section.enabled !== false);
            var sectionOpacity = sectionEnabled ? '1' : '0.35';

            html += '<div class="session-section" data-section="' + key + '" style="opacity:' + sectionOpacity + '; margin-bottom:6px;">';
            html += '  <div class="session-section-header" style="cursor:pointer; background:' + bg + '; padding:10px 12px; border:1px solid ' + border + '; border-radius:4px; display:flex; align-items:center; gap:10px;">';
            var secToggleIcon = sectionEnabled ? 'fa-toggle-on' : 'fa-toggle-off';
            var secToggleColor = sectionEnabled ? '#1DB954' : '#555';
            html += '    <i class="fas ' + secToggleIcon + ' cursor" style="color:' + secToggleColor + '; font-size:16px;" onclick="event.stopPropagation(); SessionEditor.toggleSectionEnabled(\'' + key + '\')" title="' + _t('Activer/Désactiver la section') + '"></i>';
            html += '    <span onclick="SessionEditor.toggleSection(\'' + key + '\')" style="color:' + color + '; font-weight:bold; flex-grow:1; cursor:pointer;">' + label + '</span>';
            html += '    <span onclick="SessionEditor.toggleSection(\'' + key + '\')" style="color:#888; font-size:12px; cursor:pointer;">' + count + ' ' + _t('élément(s)') + '</span>';
            html += '    <span onclick="SessionEditor.toggleSection(\'' + key + '\')" style="color:#aaa; font-size:12px; font-family:monospace; cursor:pointer;">' + dur + '</span>';
            html += '    <i class="fas ' + (isOpen ? 'fa-chevron-down' : 'fa-chevron-right') + ' session-section-chevron" style="color:#666; transition:transform 0.2s; cursor:pointer;" onclick="SessionEditor.toggleSection(\'' + key + '\')"></i>';
            html += '  </div>';
            html += '  <div class="session-section-body" data-section="' + key + '" style="' + (isOpen ? '' : 'display:none;') + ' border:1px solid ' + border + '; border-top:none; border-radius:0 0 4px 4px; padding:10px; background:' + bg + ';">';
            html += SessionEditor.renderTriggerList(triggers, key);
            html += '    <div style="margin-top:8px; display:flex; gap:6px;">';
            html += '      <button class="btn btn-xs btn-primary" onclick="SessionEditor.addMedia(\'' + key + '\')"><i class="fas fa-film"></i> ' + _t('Média') + '</button>';
            html += '      <button class="btn btn-xs btn-warning" onclick="SessionEditor.addPause(\'' + key + '\')"><i class="fas fa-pause"></i> ' + _t('Pause') + '</button>';
            html += '      <button class="btn btn-xs btn-danger" onclick="SessionEditor.addAction(\'' + key + '\')"><i class="fas fa-bolt"></i> ' + _t('Action') + '</button>';
            html += '    </div>';

            // Tops calibrés (uniquement dans la section Film)
            if (key == 'film') {
                var filmMarks = (SessionEditor.sessionData.sections.film || {}).marks || {};
                var markOrder = SessionEditor.marksMeta ? SessionEditor.marksMeta.order : [];
                var markLabels = SessionEditor.marksMeta ? SessionEditor.marksMeta.labels : {};
                html += '<div style="margin-top:10px; background:#1a1a1a; border-radius:4px; padding:8px 10px;">';
                html += '  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:5px;">';
                html += '    <span style="color:#888; font-size:10px; text-transform:uppercase; font-weight:bold;"><i class="fas fa-crosshairs"></i> ' + _t('Tops calibrés') + '</span>';
                html += '    <button class="btn btn-xs btn-default" onclick="SessionEditor.resetAllMarks()" title="' + _t('Réinitialiser tous les tops') + '"><i class="fas fa-trash"></i> ' + _t('Reset tous') + '</button>';
                html += '  </div>';
                for (var mi = 0; mi < markOrder.length; mi++) {
                    var mk = markOrder[mi];
                    var val = filmMarks[mk];
                    var valStr = (val !== null && val !== undefined) ? SessionEditor.ticksToTime(val * 10000000) : '--:--';
                    var valColor = (val !== null && val !== undefined) ? '#1DB954' : '#555';
                    html += '<div style="display:flex; align-items:center; gap:8px; padding:3px 0; border-bottom:1px solid #2a2a2a; font-size:11px;">';
                    html += '  <span style="color:#aaa; flex-grow:1;">' + (markLabels[mk] || mk) + '</span>';
                    html += '  <span style="color:' + valColor + '; font-family:monospace; min-width:60px;">' + valStr + '</span>';
                    if (val !== null && val !== undefined) {
                        html += '  <i class="fas fa-times cursor" style="color:#666; font-size:10px;" onclick="SessionEditor.resetMark(\'' + mk + '\')" title="' + _t('Réinitialiser') + '"></i>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            }

            html += '  </div>';
            html += '</div>';
        }

        html += '<div style="margin-top:12px; padding:10px; background:#1a1a1a; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">';
        html += '  <span style="color:#aaa; font-size:13px;"><i class="fas fa-clock"></i> ' + _t('Durée totale estimée') + ' : <strong style="color:#fff;" id="session-total-duration">' + SessionEditor.calculateTotalDuration() + '</strong></span>';
        html += '  <div style="display:flex; gap:6px;">';
        html += '    <button class="btn btn-sm btn-success" onclick="SessionEditor.startSession()"><i class="fas fa-play"></i> ' + _t('Lancer') + '</button>';
        html += '    <button class="btn btn-sm btn-danger" onclick="SessionEditor.stopSession()" style="display:none;" id="session-stop-btn"><i class="fas fa-stop"></i> ' + _t('Arrêter') + '</button>';
        html += '    <button class="btn btn-sm btn-default" onclick="CalibrationModal.openFromEditor()"><i class="fas fa-crosshairs"></i> ' + _t('Calibrer tops') + '</button>';
        html += '    <button class="btn btn-sm btn-default" onclick="SessionEditor.refreshDurations()"><i class="fas fa-sync"></i> ' + _t('Rafraîchir durées') + '</button>';
        html += '    <button class="btn btn-sm btn-default" onclick="SessionEditor.normalizeAudio()"><i class="fas fa-volume-up"></i> ' + _t('Normaliser le son') + '</button>';
        html += '  </div>';
        html += '</div>';
        if (SessionEditor.sessionData && SessionEditor.sessionData.audio_calibrated) {
            html += '<div style="color:#1DB954; font-size:11px; margin-top:5px;"><i class="fas fa-check"></i> ' + _t('Son normalisé') + '</div>';
        }

        // Panneau monitoring live
        html += '<div id="session-monitor" style="margin-top:10px; padding:12px; background:#111; border:1px solid #333; border-radius:4px; display:none;">';
        html += '  <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">';
        html += '    <span style="color:#1DB954; font-weight:bold; font-size:13px;"><i class="fas fa-broadcast-tower"></i> ' + _t('Séance en cours') + '</span>';
        html += '    <span id="monitor-state" style="font-size:11px; padding:2px 8px; border-radius:3px; background:#333; color:#aaa;"></span>';
        html += '  </div>';
        html += '  <div style="display:flex; gap:15px; flex-wrap:wrap; font-size:12px; color:#ccc;">';
        html += '    <div><i class="fas fa-list" style="color:#888;"></i> ' + _t('Section') + ': <strong id="monitor-section">-</strong></div>';
        html += '    <div><i class="fas fa-film" style="color:#888;"></i> ' + _t('Média') + ': <strong id="monitor-title">-</strong></div>';
        html += '    <div><i class="fas fa-clock" style="color:#888;"></i> <strong id="monitor-position">--:--</strong> / <span id="monitor-duration">--:--</span></div>';
        html += '    <div><i class="fas fa-tasks" style="color:#888;"></i> ' + _t('Progression') + ': <strong id="monitor-progress">0</strong>%</div>';
        html += '  </div>';
        html += '  <div style="margin-top:8px; height:4px; background:#333; border-radius:2px;">';
        html += '    <div id="monitor-progress-bar" style="height:100%; background:#1DB954; border-radius:2px; width:0%; transition:width 0.5s;"></div>';
        html += '  </div>';
        html += '</div>';

        $('#session-editor-container').html(html);

        // Démarrer le polling monitoring
        SessionEditor.startMonitoring();

        // Charger le profil audio actuel du lecteur
        if (SessionEditor.sessionData && SessionEditor.sessionData.player_id) {
            $.ajax({
                type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                data: { action: 'get_player_cmd_ids', player_id: SessionEditor.sessionData.player_id },
                dataType: 'json', global: false,
                success: function(data) {
                    if (data.state == 'ok' && data.result.audio_profile) {
                        $.ajax({
                            type: 'POST', url: 'core/ajax/cmd.ajax.php',
                            data: { action: 'execCmd', id: data.result.audio_profile },
                            dataType: 'json', global: false,
                            success: function(cmdData) {
                                if (cmdData.state == 'ok' && cmdData.result) {
                                    $('#session-audio-profile').val(cmdData.result);
                                }
                            }
                        });
                    }
                }
            });
        }
    },

    renderCommercial: function() {
        var playlist = SessionEditor.sessionData.playlist || [];
        var dur = SessionEditor.calculateDuration(playlist);

        // Barre d'outils en haut
        var html = '<div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#1a1a1a; border:1px solid #333; border-radius:4px; margin-bottom:12px;">';
        html += '  <div style="display:flex; align-items:center; gap:10px;">';
        html += '    <span style="color:#888; font-size:11px; text-transform:uppercase;"><i class="fas fa-bullhorn"></i> ' + _t('Profil') + '</span>';
        html += '    <select id="session-commercial-profile" style="width:auto; font-size:12px; padding:3px 8px; height:28px; background:#333; color:#fff; border:1px solid #555; border-radius:3px;" onchange="SessionEditor.setCommercialProfile(this.value)">';
        html += '      <option value="mute">' + _t('Muet') + '</option>';
        html += '      <option value="quiet">' + _t('Discret') + '</option>';
        html += '      <option value="normal">' + _t('Normal') + '</option>';
        html += '      <option value="loud">' + _t('Fort') + '</option>';
        html += '      <option value="manual">' + _t('Manuel') + '</option>';
        html += '    </select>';
        html += '  </div>';
        html += '</div>';

        html += '<div class="session-section" data-section="playlist">';
        html += '  <div style="background:#2a2a2a; padding:10px 12px; border:1px solid #333; border-radius:4px 4px 0 0; display:flex; align-items:center; gap:10px;">';
        html += '    <span style="color:#1DB954; font-size:16px;">●</span>';
        html += '    <span style="color:#1DB954; font-weight:bold; flex-grow:1;">' + _t('Playlist') + '</span>';
        html += '    <span style="color:#888; font-size:12px;">' + playlist.length + ' ' + _t('média(s)') + '</span>';
        html += '    <span style="color:#aaa; font-size:12px; font-family:monospace;">' + dur + '</span>';
        html += '  </div>';
        html += '  <div style="border:1px solid #333; border-top:none; border-radius:0 0 4px 4px; padding:10px; background:#222;">';
        html += SessionEditor.renderTriggerList(playlist, 'playlist');
        html += '    <div style="margin-top:8px;">';
        html += '      <button class="btn btn-xs btn-primary" onclick="SessionEditor.addMedia(\'playlist\')"><i class="fas fa-film"></i> ' + _t('Média') + '</button>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        // Sélecteur de boucle
        var loopVal = SessionEditor.sessionData.loop;
        var loopMode = 'infinite';
        var loopCount = 1;
        if (loopVal === false) loopMode = 'none';
        else if (loopVal === true || loopVal === 'infinite') loopMode = 'infinite';
        else if (typeof loopVal === 'number' && loopVal > 0) { loopMode = 'count'; loopCount = loopVal; }

        html += '<div style="margin-top:8px; padding:8px 12px; background:#1a1a1a; border:1px solid #333; border-radius:4px; display:flex; align-items:center; gap:10px;">';
        html += '  <span style="color:#888; font-size:11px; text-transform:uppercase;"><i class="fas fa-redo"></i> ' + _t('Boucle') + '</span>';
        html += '  <select id="session-loop-mode" style="width:auto; font-size:12px; padding:3px 8px; height:28px; background:#333; color:#fff; border:1px solid #555; border-radius:3px;" onchange="SessionEditor.setLoopMode(this.value)">';
        html += '    <option value="none"' + (loopMode == 'none' ? ' selected' : '') + '>' + _t('Pas de boucle') + '</option>';
        html += '    <option value="infinite"' + (loopMode == 'infinite' ? ' selected' : '') + '>' + _t('Boucle infinie') + '</option>';
        html += '    <option value="count"' + (loopMode == 'count' ? ' selected' : '') + '>' + _t('Nombre de boucles') + '</option>';
        html += '  </select>';
        html += '  <input type="number" id="session-loop-count" min="1" max="999" value="' + loopCount + '" style="width:60px; font-size:12px; padding:3px; height:28px; background:#333; color:#fff; border:1px solid #555; border-radius:3px;' + (loopMode != 'count' ? ' display:none;' : '') + '" onchange="SessionEditor.setLoopCount(this.value)" />';
        html += '</div>';

        html += '<div style="margin-top:8px; padding:10px; background:#1a1a1a; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">';
        html += '  <span style="color:#aaa; font-size:13px;"><i class="fas fa-clock"></i> ' + _t('Durée totale') + ' : <strong style="color:#fff;" id="session-total-duration">' + dur + '</strong></span>';
        html += '  <div style="display:flex; gap:6px;">';
        html += '    <button class="btn btn-sm btn-success" onclick="SessionEditor.startSession()"><i class="fas fa-play"></i> ' + _t('Lancer') + '</button>';
        html += '    <button class="btn btn-sm btn-danger" onclick="SessionEditor.stopSession()" style="display:none;" id="session-stop-btn"><i class="fas fa-stop"></i> ' + _t('Arrêter') + '</button>';
        html += '    <button class="btn btn-sm btn-default" onclick="SessionEditor.refreshDurations()"><i class="fas fa-sync"></i> ' + _t('Rafraîchir durées') + '</button>';
        html += '    <button class="btn btn-sm btn-default" onclick="SessionEditor.normalizeAudio()"><i class="fas fa-volume-up"></i> ' + _t('Normaliser le son') + '</button>';
        html += '  </div>';
        html += '</div>';

        // Monitoring live (même que cinéma)
        html += '<div id="session-monitor" style="margin-top:10px; padding:12px; background:#111; border:1px solid #333; border-radius:4px; display:none;">';
        html += '  <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">';
        html += '    <span style="color:#1DB954; font-weight:bold; font-size:13px;"><i class="fas fa-broadcast-tower"></i> ' + _t('Diffusion en cours') + '</span>';
        html += '    <span id="monitor-state" style="font-size:11px; padding:2px 8px; border-radius:3px; background:#333; color:#aaa;"></span>';
        html += '  </div>';
        html += '  <div style="display:flex; gap:15px; flex-wrap:wrap; font-size:12px; color:#ccc;">';
        html += '    <div><i class="fas fa-film" style="color:#888;"></i> ' + _t('Média') + ': <strong id="monitor-title">-</strong></div>';
        html += '    <div><i class="fas fa-clock" style="color:#888;"></i> <strong id="monitor-position">--:--</strong> / <span id="monitor-duration">--:--</span></div>';
        html += '    <div><i class="fas fa-tasks" style="color:#888;"></i> ' + _t('Progression') + ': <strong id="monitor-progress">0</strong>%</div>';
        html += '    <div><i class="fas fa-redo" style="color:#888;"></i> ' + _t('Boucle') + ': <strong id="monitor-loop">-</strong></div>';
        html += '  </div>';
        html += '  <div style="margin-top:8px; height:4px; background:#333; border-radius:2px;">';
        html += '    <div id="monitor-progress-bar" style="height:100%; background:#1DB954; border-radius:2px; width:0%; transition:width 0.5s;"></div>';
        html += '  </div>';
        html += '</div>';

        $('#session-editor-container').html(html);

        // Démarrer le polling monitoring
        SessionEditor.startMonitoring();

        // Charger le profil commercial actuel
        if (SessionEditor.sessionData && SessionEditor.sessionData.player_id) {
            $.ajax({
                type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                data: { action: 'get_player_cmd_ids', player_id: SessionEditor.sessionData.player_id },
                dataType: 'json', global: false,
                success: function(data) {
                    if (data.state == 'ok' && data.result.commercial_audio_profile) {
                        $.ajax({
                            type: 'POST', url: 'core/ajax/cmd.ajax.php',
                            data: { action: 'execCmd', id: data.result.commercial_audio_profile },
                            dataType: 'json', global: false,
                            success: function(cmdData) {
                                if (cmdData.state == 'ok' && cmdData.result) {
                                    $('#session-commercial-profile').val(cmdData.result);
                                }
                            }
                        });
                    }
                }
            });
        }
    },

    renderTriggerList: function(triggers, sectionKey) {
        if (!triggers || triggers.length == 0) {
            return '<div style="color:#555; font-size:12px; padding:5px; text-align:center;">' + _t('Aucun élément') + '</div>';
        }
        var html = '';
        for (var i = 0; i < triggers.length; i++) {
            var t = triggers[i];
            var isEnabled = (t.enabled !== false);
            var opacity = isEnabled ? '1' : '0.35';
            var icon = '', label = '', durStr = '';
            if (t.type == 'media') {
                icon = '<i class="fas fa-film" style="color:#3498db;"></i>';
                label = t.name || t.media_id;
                if (t.duration_ticks) durStr = SessionEditor.ticksToTime(t.duration_ticks);
            } else if (t.type == 'pause') {
                icon = '<i class="fas fa-pause-circle" style="color:#f39c12;"></i>';
                label = '<span class="cursor" onclick="event.stopPropagation(); SessionEditor.editPause(\'' + sectionKey + '\',' + i + ')" title="' + _t('Cliquer pour modifier') + '">' + (t.duration > 0 ? _t('Pause') + ' ' + t.duration + 's' : _t('Pause illimitée')) + ' <i class="fas fa-pen" style="font-size:9px; color:#666;"></i></span>';
            } else if (t.type == 'command') {
                icon = '<i class="fas fa-bolt" style="color:#e74c3c;"></i>';
                label = '<span class="cursor" onclick="event.stopPropagation(); SessionEditor.editCommand(\'' + sectionKey + '\',' + i + ')" title="' + _t('Cliquer pour modifier') + '">' + (t.label || (_t('Commande') + ' #' + t.cmd_id)) + ' <i class="fas fa-pen" style="font-size:9px; color:#666;"></i></span>';
            } else if (t.type == 'scenario') {
                icon = '<i class="fas fa-cogs" style="color:#9b59b6;"></i>';
                label = '<span class="cursor" onclick="event.stopPropagation(); SessionEditor.editScenario(\'' + sectionKey + '\',' + i + ')" title="' + _t('Cliquer pour modifier') + '">' + (t.label || (_t('Scénario') + ' #' + t.scenario_id)) + ' <i class="fas fa-pen" style="font-size:9px; color:#666;"></i></span>';
            }
            var toggleIcon = isEnabled ? 'fa-toggle-on' : 'fa-toggle-off';
            var toggleColor = isEnabled ? '#1DB954' : '#555';
            html += '<div style="background:#1a1a1a; padding:6px 10px; border-radius:3px; margin-bottom:3px; display:flex; align-items:center; gap:8px; font-size:12px; color:#ccc; opacity:' + opacity + ';">';
            html += '  <i class="fas ' + toggleIcon + ' cursor" style="color:' + toggleColor + '; font-size:14px;" onclick="SessionEditor.toggleTrigger(\'' + sectionKey + '\',' + i + ')" title="' + _t('Activer/Désactiver') + '"></i>';
            html += '  ' + icon + ' <span style="flex-grow:1;">' + label + '</span>';
            // Badges techniques (résolution vidéo + audio)
            if (t.type == 'media') {
                var badgeStyle = 'background:#333; color:#ddd; padding:1px 5px; border-radius:3px; border:1px solid #555; font-size:10px; letter-spacing:0.3px;';
                if (t.video_res) {
                    var resColor = (t.video_res === '4K') ? '#1DB954' : '#ddd';
                    html += '  <span style="' + badgeStyle + ' color:' + resColor + ';">' + t.video_res + '</span>';
                }
                if (t.audio_info) {
                    html += '  <span style="' + badgeStyle + '">' + t.audio_info + '</span>';
                }
            }
            if (t.type == 'media' && t.volume !== undefined && t.volume !== null && t.volume !== '') {
                html += '  <span class="cursor" style="color:#f39c12; font-size:10px;" onclick="event.stopPropagation(); SessionEditor.editVolume(\'' + sectionKey + '\',' + i + ')" title="' + _t('Volume forcé') + '"><i class="fas fa-volume-up"></i> ' + t.volume + '</span>';
            } else if (t.type == 'media' && t.volume_auto !== undefined && t.volume_auto !== null && t.volume_auto !== '') {
                html += '  <span class="cursor" style="color:#1DB954; font-size:10px;" onclick="event.stopPropagation(); SessionEditor.editVolume(\'' + sectionKey + '\',' + i + ')" title="' + _t('Volume auto (LUFS)') + '"><i class="fas fa-volume-up"></i> auto:' + t.volume_auto + '</span>';
            } else if (t.type == 'media') {
                html += '  <span class="cursor" style="color:#555; font-size:10px;" onclick="event.stopPropagation(); SessionEditor.editVolume(\'' + sectionKey + '\',' + i + ')" title="' + _t('Définir le volume (optionnel)') + '"><i class="fas fa-volume-off"></i></span>';
            }
            if (durStr) html += '  <span style="color:#666; font-size:11px;">' + durStr + '</span>';
            html += '  <span style="display:flex; gap:3px;">';
            if (i > 0) html += '    <i class="fas fa-arrow-up cursor" style="color:#666;" onclick="SessionEditor.moveTrigger(\'' + sectionKey + '\',' + i + ',-1)"></i>';
            if (i < triggers.length - 1) html += '    <i class="fas fa-arrow-down cursor" style="color:#666;" onclick="SessionEditor.moveTrigger(\'' + sectionKey + '\',' + i + ',1)"></i>';
            html += '    <i class="fas fa-times cursor" style="color:#c0392b;" onclick="SessionEditor.removeTrigger(\'' + sectionKey + '\',' + i + ')"></i>';
            html += '  </span>';
            html += '</div>';
        }
        return html;
    },

    toggleTrigger: function(sectionKey, index) {
        var triggers = SessionEditor.getTriggers(sectionKey);
        triggers[index].enabled = (triggers[index].enabled === false) ? true : false;
        SessionEditor.setTriggers(sectionKey, triggers);
        SessionEditor.save(function() { SessionEditor.reload(); });
    },

    getTriggers: function(sectionKey) {
        if (SessionEditor.sessionType == 'commercial') return SessionEditor.sessionData.playlist;
        return SessionEditor.sessionData.sections[sectionKey].triggers;
    },

    setTriggers: function(sectionKey, triggers) {
        if (SessionEditor.sessionType == 'commercial') {
            SessionEditor.sessionData.playlist = triggers;
        } else {
            SessionEditor.sessionData.sections[sectionKey].triggers = triggers;
        }
    },

    addMedia: function(sectionKey) {
        if (typeof JellyfinBrowser === 'undefined') {
            bootbox.alert(_t('Explorateur de bibliothèque non disponible'));
            return;
        }
        // On ouvre le browser avec un callback custom
        SessionEditor._pendingSection = sectionKey;
        JellyfinBrowser.open(SessionEditor.eqLogicId, '');
        // Override temporaire du bouton "Lire maintenant" → "Ajouter à la séance"
        setTimeout(function() {
            var $playBtn = $('.jellyfin-modal-fullscreen .btn-success');
            $playBtn.off('click').html('<i class="fas fa-plus"></i> ' + _t('Ajouter à la séance'));
            $playBtn.on('click', function() {
                if (JellyfinBrowser.selectedItem) {
                    var item = JellyfinBrowser.selectedItem;
                    // Récupérer la durée depuis les données affichées
                    var durationTicks = 0;
                    var durText = $('#sel-duration').text();
                    // On récupère depuis le résultat brut si dispo
                    var triggers = SessionEditor.getTriggers(SessionEditor._pendingSection);
                    triggers.push({
                        type: 'media',
                        media_id: item.Id,
                        name: item.Name,
                        img_tag: item.ImgTag || '',
                        duration_ticks: item.RunTimeTicks || 0,
                        video_res: item.VideoRes || '',
                        audio_info: item.AudioInfo || ''
                    });
                    SessionEditor.setTriggers(SessionEditor._pendingSection, triggers);
                    SessionEditor.save(function() {
                        SessionEditor.reload();
                    });
                    bootbox.hideAll();
                } else {
                    bootbox.alert(_t('Veuillez sélectionner un média.'));
                }
                return false;
            });
        }, 500);
    },

    addPause: function(sectionKey) {
        bootbox.prompt({
            title: _t('Durée de la pause (secondes, 0 = illimitée)'),
            value: '0',
            callback: function(result) {
                if (result === null) return;
                var duration = parseInt(result) || 0;
                var triggers = SessionEditor.getTriggers(sectionKey);
                triggers.push({ type: 'pause', duration: duration });
                SessionEditor.setTriggers(sectionKey, triggers);
                SessionEditor.save(function() { SessionEditor.reload(); });
            }
        });
    },

    addAction: function(sectionKey) {
        bootbox.dialog({
            title: _t('Ajouter une action'),
            message: '<div class="form-group">' +
                '<label>' + _t('Type') + '</label>' +
                '<select id="action_type_select" class="form-control">' +
                '<option value="command">' + _t('Commande équipement') + '</option>' +
                '<option value="scenario">' + _t('Scénario') + '</option>' +
                '</select></div>' +
                '<div class="form-group" id="action_cmd_group">' +
                '<label>' + _t('Commande') + '</label>' +
                '<div class="input-group">' +
                '<input type="text" id="action_cmd_input" class="form-control" readonly placeholder="' + _t('Cliquez pour choisir') + '">' +
                '<span class="input-group-btn"><button class="btn btn-default" id="action_cmd_pick" type="button"><i class="fas fa-list-alt"></i></button></span>' +
                '</div></div>' +
                '<div class="form-group" id="action_scenario_group" style="display:none;">' +
                '<label>' + _t('Scénario') + '</label>' +
                '<select id="action_scenario_select" class="form-control"></select>' +
                '</div>',
            buttons: {
                cancel: { label: _t('Annuler'), className: 'btn-default' },
                confirm: {
                    label: _t('Ajouter'), className: 'btn-success',
                    callback: function() {
                        var actionType = $('#action_type_select').val();
                        var triggers = SessionEditor.getTriggers(sectionKey);
                        if (actionType == 'command') {
                            var cmdId = $('#action_cmd_input').data('cmd_id');
                            var cmdLabel = $('#action_cmd_input').val();
                            if (!cmdId) { bootbox.alert(_t('Veuillez sélectionner une commande')); return false; }
                            triggers.push({ type: 'command', cmd_id: parseInt(cmdId), label: cmdLabel });
                        } else {
                            var scenarioId = $('#action_scenario_select').val();
                            var scenarioLabel = $('#action_scenario_select option:selected').text();
                            if (!scenarioId) { bootbox.alert(_t('Veuillez sélectionner un scénario')); return false; }
                            triggers.push({ type: 'scenario', scenario_id: parseInt(scenarioId), label: scenarioLabel });
                        }
                        SessionEditor.setTriggers(sectionKey, triggers);
                        SessionEditor.save(function() { SessionEditor.reload(); });
                    }
                }
            }
        });
        // Toggle affichage commande/scénario
        setTimeout(function() {
            $('#action_type_select').on('change', function() {
                if ($(this).val() == 'command') {
                    $('#action_cmd_group').show();
                    $('#action_scenario_group').hide();
                } else {
                    $('#action_cmd_group').hide();
                    $('#action_scenario_group').show();
                }
            });
            // Charger les scénarios
            if (typeof jeedom !== 'undefined') {
                jeedom.scenario.all({
                    error: function(e) {},
                    success: function(scenarios) {
                        var $sel = $('#action_scenario_select');
                        $sel.empty().append('<option value="">' + _t('Choisir...') + '</option>');
                        scenarios.forEach(function(s) { $sel.append('<option value="' + s.id + '">' + s.humanName + '</option>'); });
                    }
                });
            }
            // Picker commande Jeedom — on masque la bootbox pour que le sélecteur Jeedom soit accessible
            $('#action_cmd_pick').on('click', function() {
                var $bootboxModal = $(this).closest('.modal');
                $bootboxModal.css('z-index', 0);
                $bootboxModal.find('.modal-backdrop').css('z-index', 0);
                jeedom.cmd.getSelectModal({ cmd: { type: 'action' } }, function(result) {
                    $('#action_cmd_input').val(result.human).data('cmd_id', result.cmd.id);
                    $bootboxModal.css('z-index', '');
                    $bootboxModal.find('.modal-backdrop').css('z-index', '');
                });
            });
        }, 300);
    },

    moveTrigger: function(sectionKey, index, direction) {
        var triggers = SessionEditor.getTriggers(sectionKey);
        var newIndex = index + direction;
        if (newIndex < 0 || newIndex >= triggers.length) return;
        var tmp = triggers[index];
        triggers[index] = triggers[newIndex];
        triggers[newIndex] = tmp;
        SessionEditor.setTriggers(sectionKey, triggers);
        SessionEditor.save(function() { SessionEditor.reload(); });
    },

    removeTrigger: function(sectionKey, index) {
        var triggers = SessionEditor.getTriggers(sectionKey);
        var name = triggers[index].name || triggers[index].label || triggers[index].type;
        bootbox.confirm(_t('Supprimer') + ' : <b>' + name + '</b> ?', function(ok) {
            if (!ok) return;
            triggers.splice(index, 1);
            SessionEditor.setTriggers(sectionKey, triggers);
            SessionEditor.save(function() { SessionEditor.reload(); });
        });
    },

    setLoopMode: function(mode) {
        if (!SessionEditor.sessionData) return;
        if (mode == 'none') SessionEditor.sessionData.loop = false;
        else if (mode == 'infinite') SessionEditor.sessionData.loop = true;
        else if (mode == 'count') {
            var count = parseInt($('#session-loop-count').val()) || 1;
            SessionEditor.sessionData.loop = count;
            $('#session-loop-count').show();
        }
        if (mode != 'count') $('#session-loop-count').hide();
        SessionEditor.save();
    },

    setLoopCount: function(count) {
        if (!SessionEditor.sessionData) return;
        SessionEditor.sessionData.loop = parseInt(count) || 1;
        SessionEditor.save();
    },

    setCommercialProfile: function(profile) {
        if (!SessionEditor.sessionData || !SessionEditor.sessionData.player_id) return;
        $.ajax({
            type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'set_audio_profile', player_id: SessionEditor.sessionData.player_id, profile: profile, type: 'commercial' },
            dataType: 'json',
            success: function(data) {
                if (data.state == 'ok') {
                    $('#div_alert').showAlert({ message: _t('Profil commercial') + ' : ' + profile, level: 'success' });
                } else {
                    $('#div_alert').showAlert({ message: data.result || 'Erreur', level: 'danger' });
                }
            }
        });
    },

    setAudioProfile: function(profile) {
        if (!SessionEditor.sessionData || !SessionEditor.sessionData.player_id) return;
        $.ajax({
            type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'set_audio_profile', player_id: SessionEditor.sessionData.player_id, profile: profile, type: 'cinema' },
            dataType: 'json',
            success: function(data) {
                if (data.state == 'ok') {
                    $('#div_alert').showAlert({ message: _t('Profil audio') + ' : ' + profile, level: 'success' });
                } else {
                    $('#div_alert').showAlert({ message: data.result || 'Erreur', level: 'danger' });
                }
            }
        });
    },

    collapseAll: function() {
        $('.session-section-body').slideUp(200);
        $('.session-section-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
        SessionEditor._openSections = {};
    },

    toggleSection: function(sectionKey) {
        var $body = $('.session-section-body[data-section="' + sectionKey + '"]');
        var $chevron = $body.prev().find('.session-section-chevron');
        var isVisible = $body.is(':visible');
        SessionEditor._openSections[sectionKey] = !isVisible;
        $body.slideToggle(200);
        $chevron.toggleClass('fa-chevron-right fa-chevron-down');
    },

    save: function(callback) {
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'save_session_data', id: SessionEditor.eqLogicId, session_data: JSON.stringify(SessionEditor.sessionData) },
            dataType: 'json',
            success: function(data) {
                if (callback) callback();
            }
        });
    },

    reload: function() {
        SessionEditor.load(SessionEditor.eqLogicId);
    },

    _monitorInterval: null,

    startMonitoring: function() {
        SessionEditor.stopMonitoring();
        SessionEditor.pollStatus();
        SessionEditor._monitorInterval = setInterval(SessionEditor.pollStatus, 2000);
    },

    stopMonitoring: function() {
        if (SessionEditor._monitorInterval) {
            clearInterval(SessionEditor._monitorInterval);
            SessionEditor._monitorInterval = null;
        }
    },

    pollStatus: function() {
        if (!SessionEditor.eqLogicId) return;
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'get_session_status', id: SessionEditor.eqLogicId },
            dataType: 'json',
            global: false,
            success: function(data) {
                if (data.state != 'ok') return;
                var r = data.result;
                var $monitor = $('#session-monitor');
                if (r.state == 'playing' || r.state == 'paused') {
                    $monitor.show();
                    $('#session-stop-btn').show();
                    var stateColor = r.state == 'playing' ? '#1DB954' : '#f39c12';
                    var stateLabel = r.state == 'playing' ? 'Playing' : 'Paused';
                    $('#monitor-state').text(stateLabel).css({ background: stateColor, color: '#fff' });
                    $('#monitor-section').text(r.current_section || '-');
                    $('#monitor-progress').text(r.progress || 0);
                    $('#monitor-progress-bar').css('width', (r.progress || 0) + '%');
                    if (r.player) {
                        $('#monitor-title').text(r.player.title || '-');
                        $('#monitor-position').text(r.player.position || '--:--');
                        $('#monitor-duration').text(r.player.duration || '--:--');
                    }
                    if (r.engine_state) {
                        var es = r.engine_state;
                        // Afficher le compteur de boucle
                        if (es.loop_current) {
                            var loopTotal = SessionEditor.sessionData ? SessionEditor.sessionData.loop : true;
                            var loopLabel = es.loop_current;
                            if (loopTotal === true) loopLabel += '/' + _t('infini');
                            else if (typeof loopTotal === 'number') loopLabel += '/' + loopTotal;
                            else loopLabel += '/1';
                            $('#monitor-loop').text(loopLabel);
                        }
                        var debugParts = [];
                        if (es.current_section) debugParts.push('sec:' + es.current_section);
                        if (es.current_trigger_index !== undefined) debugParts.push('idx:' + es.current_trigger_index);
                        if (es.current_media_id) debugParts.push('media:' + es.current_media_id.substring(0, 8) + '...');
                        if (es.queued) debugParts.push('queued');
                        if (es.stopped_since) debugParts.push('stopped_since:' + es.stopped_since);
                    }
                } else {
                    $monitor.hide();
                    $('#session-stop-btn').hide();
                }
            }
        });
    },

    stopSession: function() {
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'stop_session', id: SessionEditor.eqLogicId },
            dataType: 'json',
            success: function(data) {
                if (data.state == 'ok') {
                    $('#div_alert').showAlert({ message: _t('Séance arrêtée'), level: 'success' });
                    SessionEditor.pollStatus();
                }
            }
        });
    },

    editVolume: function(sectionKey, index) {
        var triggers = SessionEditor.getTriggers(sectionKey);
        var t = triggers[index];
        var currentVol = (t.volume !== undefined && t.volume !== null && t.volume !== '') ? t.volume : '';
        bootbox.dialog({
            title: '<i class="fas fa-volume-up"></i> ' + _t('Volume ampli') + ' — ' + (t.name || ''),
            message: '<div class="form-group">' +
                '<label>' + _t('Volume (0-100, vide = défaut lecteur)') + '</label>' +
                '<input type="number" id="input_trigger_volume" class="form-control" min="0" max="100" value="' + currentVol + '" placeholder="' + _t('Défaut') + '">' +
                '</div>' +
                '<div class="text-muted" style="font-size:11px;">' + _t('Laissez vide pour utiliser le volume par défaut du lecteur. Nécessite une commande ampli configurée sur le lecteur.') + '</div>',
            buttons: {
                clear: { label: _t('Effacer'), className: 'btn-warning', callback: function() {
                    delete triggers[index].volume;
                    SessionEditor.setTriggers(sectionKey, triggers);
                    SessionEditor.save(function() { SessionEditor.reload(); });
                }},
                cancel: { label: _t('Annuler'), className: 'btn-default' },
                confirm: { label: _t('Valider'), className: 'btn-success', callback: function() {
                    var val = $('#input_trigger_volume').val();
                    if (val !== '' && val !== null) {
                        triggers[index].volume = parseInt(val);
                    } else {
                        delete triggers[index].volume;
                    }
                    SessionEditor.setTriggers(sectionKey, triggers);
                    SessionEditor.save(function() { SessionEditor.reload(); });
                }}
            }
        });
    },

    editPause: function(sectionKey, index) {
        var triggers = SessionEditor.getTriggers(sectionKey);
        var t = triggers[index];
        bootbox.prompt({
            title: _t('Durée de la pause (secondes, 0 = illimitée)'),
            value: String(t.duration || 0),
            callback: function(result) {
                if (result === null) return;
                triggers[index].duration = parseInt(result) || 0;
                SessionEditor.setTriggers(sectionKey, triggers);
                SessionEditor.save(function() { SessionEditor.reload(); });
            }
        });
    },

    editCommand: function(sectionKey, index) {
        var triggers = SessionEditor.getTriggers(sectionKey);
        var $bootboxModal = null;
        bootbox.dialog({
            title: _t('Modifier la commande'),
            message: '<div class="input-group">' +
                '<input type="text" id="edit_cmd_input" class="form-control" value="' + (triggers[index].label || '').replace(/"/g, '&quot;') + '" readonly>' +
                '<span class="input-group-btn"><button class="btn btn-default" id="edit_cmd_pick" type="button"><i class="fas fa-list-alt"></i></button></span>' +
                '</div>',
            buttons: {
                cancel: { label: _t('Annuler'), className: 'btn-default' },
                confirm: { label: _t('Valider'), className: 'btn-success', callback: function() {
                    var cmdId = $('#edit_cmd_input').data('cmd_id');
                    if (cmdId) {
                        triggers[index].cmd_id = parseInt(cmdId);
                        triggers[index].label = $('#edit_cmd_input').val();
                        SessionEditor.setTriggers(sectionKey, triggers);
                        SessionEditor.save(function() { SessionEditor.reload(); });
                    }
                }}
            }
        });
        setTimeout(function() {
            $('#edit_cmd_pick').on('click', function() {
                var $modal = $(this).closest('.modal');
                $modal.css('z-index', 0);
                jeedom.cmd.getSelectModal({ cmd: { type: 'action' } }, function(result) {
                    $('#edit_cmd_input').val(result.human).data('cmd_id', result.cmd.id);
                    $modal.css('z-index', '');
                });
            });
        }, 300);
    },

    editScenario: function(sectionKey, index) {
        var triggers = SessionEditor.getTriggers(sectionKey);
        bootbox.dialog({
            title: _t('Modifier le scénario'),
            message: '<select id="edit_scenario_select" class="form-control"></select>',
            buttons: {
                cancel: { label: _t('Annuler'), className: 'btn-default' },
                confirm: { label: _t('Valider'), className: 'btn-success', callback: function() {
                    var scenarioId = $('#edit_scenario_select').val();
                    if (scenarioId) {
                        triggers[index].scenario_id = parseInt(scenarioId);
                        triggers[index].label = $('#edit_scenario_select option:selected').text();
                        SessionEditor.setTriggers(sectionKey, triggers);
                        SessionEditor.save(function() { SessionEditor.reload(); });
                    }
                }}
            }
        });
        setTimeout(function() {
            jeedom.scenario.all({
                success: function(scenarios) {
                    var $sel = $('#edit_scenario_select');
                    $sel.empty();
                    scenarios.forEach(function(s) {
                        var selected = (s.id == triggers[index].scenario_id) ? ' selected' : '';
                        $sel.append('<option value="' + s.id + '"' + selected + '>' + s.humanName + '</option>');
                    });
                }
            });
        }, 300);
    },

    toggleSectionEnabled: function(sectionKey) {
        if (!SessionEditor.sessionData || !SessionEditor.sessionData.sections) return;
        var section = SessionEditor.sessionData.sections[sectionKey];
        if (!section) return;
        section.enabled = (section.enabled === false) ? true : false;
        SessionEditor.save(function() { SessionEditor.reload(); });
    },

    resetMark: function(markName) {
        if (!SessionEditor.sessionData || !SessionEditor.sessionData.sections || !SessionEditor.sessionData.sections.film) return;
        SessionEditor.sessionData.sections.film.marks[markName] = null;
        SessionEditor.save(function() { SessionEditor.reload(); });
    },

    resetAllMarks: function() {
        if (!SessionEditor.sessionData || !SessionEditor.sessionData.sections || !SessionEditor.sessionData.sections.film) return;
        bootbox.confirm(_t('Réinitialiser tous les tops du film ?'), function(ok) {
            if (!ok) return;
            var markOrder = SessionEditor.marksMeta ? SessionEditor.marksMeta.order : [];
            for (var i = 0; i < markOrder.length; i++) {
                SessionEditor.sessionData.sections.film.marks[markOrder[i]] = null;
            }
            SessionEditor.save(function() { SessionEditor.reload(); });
        });
    },

    normalizeAudio: function() {
        $.ajax({
            type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'check_ffmpeg' }, dataType: 'json',
            success: function(data) {
                if (data.state != 'ok' || !data.result.available) {
                    bootbox.alert(_t('ffmpeg n\'est pas installé. Lancez l\'installation des dépendances.'));
                    return;
                }
                bootbox.dialog({
                    title: '<i class="fas fa-volume-up"></i> ' + _t('Normalisation audio'),
                    message: _t('Choisissez le mode d\'analyse :'),
                    buttons: {
                        quick: { label: _t('Analyse rapide (~10-30s/clip)'), className: 'btn-primary',
                            callback: function() { SessionEditor._runAnalysis('quick'); } },
                        complete: { label: _t('Analyse complète (~30-60s/clip)'), className: 'btn-default',
                            callback: function() { SessionEditor._runAnalysis('complete'); } },
                        cancel: { label: _t('Annuler'), className: 'btn-default' }
                    }
                });
            }
        });
    },

    _runAnalysis: function(mode) {
        var html = '<div id="audio-analysis-progress" style="padding:20px;">' +
            '<div style="text-align:center; margin-bottom:15px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:#1DB954;"></i></div>' +
            '<div id="analysis-status" style="color:#aaa; text-align:center;">' + _t('Démarrage...') + '</div>' +
            '<div style="margin-top:10px; height:6px; background:#333; border-radius:3px;">' +
            '  <div id="analysis-bar" style="height:100%; background:#1DB954; border-radius:3px; width:0%; transition:width 0.5s;"></div>' +
            '</div>' +
            '<div id="analysis-log" style="margin-top:15px; max-height:200px; overflow-y:auto; font-size:11px; color:#888;"></div>' +
            '</div>';

        bootbox.dialog({
            title: '<i class="fas fa-volume-up"></i> ' + _t('Analyse audio en cours'),
            message: html, closeButton: false, buttons: {}
        });

        $.ajax({
            type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'analyze_session_audio', id: SessionEditor.eqLogicId, mode: mode },
            dataType: 'json', global: false, timeout: 600000
        });

        SessionEditor._analysisPoll = setInterval(function() {
            $.ajax({
                type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                data: { action: 'get_analysis_progress', id: SessionEditor.eqLogicId },
                dataType: 'json', global: false,
                success: function(data) {
                    if (data.state != 'ok') return;
                    var p = data.result;
                    if (p.status == 'analyzing') {
                        var pct = p.total_clips > 0 ? Math.round((p.current_index / p.total_clips) * 100) : 0;
                        $('#analysis-bar').css('width', pct + '%');
                        $('#analysis-status').html('<i class="fas fa-music"></i> ' + p.current_clip + ' (' + p.current_index + '/' + p.total_clips + ')');
                        var logHtml = '';
                        (p.results || []).forEach(function(r) { logHtml += '<div style="color:#1DB954;">\u2713 LUFS: ' + r.lufs.toFixed(1) + ' \u2192 vol: ' + r.volume_auto + '</div>'; });
                        (p.errors || []).forEach(function(e) { logHtml += '<div style="color:#e74c3c;">\u2717 ' + e.name + ': ' + e.error + '</div>'; });
                        $('#analysis-log').html(logHtml);
                    } else if (p.status == 'done') {
                        clearInterval(SessionEditor._analysisPoll);
                        var total = (p.results || []).length;
                        var errs = (p.errors || []).length;
                        // Afficher TOUS les résultats (y compris le dernier)
                        var logHtml = '';
                        (p.results || []).forEach(function(r) { logHtml += '<div style="color:#1DB954;">\u2713 LUFS: ' + r.lufs.toFixed(1) + ' \u2192 vol: ' + r.volume_auto + '</div>'; });
                        (p.errors || []).forEach(function(e) { logHtml += '<div style="color:#e74c3c;">\u2717 ' + e.name + ': ' + e.error + '</div>'; });
                        $('#analysis-log').html(logHtml);
                        $('#analysis-bar').css('width', '100%');
                        // Remplacer le spinner par un check
                        $('#audio-analysis-progress > div:first-child').html('<i class="fas fa-check-circle fa-2x" style="color:#1DB954;"></i>');
                        $('#analysis-status').html('<i class="fas fa-check" style="color:#1DB954;"></i> ' + total + ' clip(s) ' + _t('normalisé(s)') + (errs > 0 ? ' (' + errs + ' ' + _t('erreur(s)') + ')' : ''));
                        $('#audio-analysis-progress').append('<div style="text-align:center; margin-top:15px;"><button class="btn btn-sm btn-success" onclick="bootbox.hideAll(); SessionEditor.reload();"><i class="fas fa-check"></i> ' + _t('Fermer') + '</button></div>');
                    }
                }
            });
        }, 2000);
    },

    refreshDurations: function() {
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'refresh_session_durations', id: SessionEditor.eqLogicId },
            dataType: 'json',
            success: function(data) {
                if (data.state == 'ok') {
                    $('#div_alert').showAlert({ message: data.result.updated + ' ' + _t('durée(s) mise(s) à jour'), level: 'success' });
                    SessionEditor.reload();
                } else {
                    $('#div_alert').showAlert({ message: data.result, level: 'danger' });
                }
            }
        });
    },

    startSession: function() {
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'start_session', id: SessionEditor.eqLogicId },
            dataType: 'json',
            success: function(data) {
                if (data.state == 'ok') {
                    $('#div_alert').showAlert({ message: _t('Séance lancée !'), level: 'success' });
                } else {
                    $('#div_alert').showAlert({ message: data.result, level: 'danger' });
                }
            }
        });
    },

    ticksToTime: function(ticks) {
        if (!ticks) return '0:00';
        var totalSec = Math.floor(ticks / 10000000);
        var h = Math.floor(totalSec / 3600);
        var m = Math.floor((totalSec % 3600) / 60);
        var s = totalSec % 60;
        if (h > 0) return h + 'h' + String(m).padStart(2, '0') + 'm' + String(s).padStart(2, '0') + 's';
        if (m > 0) return m + 'm' + String(s).padStart(2, '0') + 's';
        return s + 's';
    },

    calculateDuration: function(triggers) {
        var totalTicks = 0;
        for (var i = 0; i < triggers.length; i++) {
            if (triggers[i].enabled === false) continue;
            if (triggers[i].type == 'media' && triggers[i].duration_ticks) {
                totalTicks += triggers[i].duration_ticks;
            } else if (triggers[i].type == 'pause' && triggers[i].duration > 0) {
                totalTicks += triggers[i].duration * 10000000;
            }
        }
        return SessionEditor.ticksToTime(totalTicks);
    },

    calculateTotalDuration: function() {
        var totalTicks = 0;
        if (SessionEditor.sessionType == 'commercial') {
            var pl = SessionEditor.sessionData.playlist || [];
            for (var i = 0; i < pl.length; i++) {
                if (pl[i].enabled === false) continue;
                if (pl[i].duration_ticks) totalTicks += pl[i].duration_ticks;
            }
        } else {
            var order = SessionEditor.sectionsMeta.order;
            for (var s = 0; s < order.length; s++) {
                var sec = SessionEditor.sessionData.sections[order[s]] || {};
                if (sec.enabled === false) continue;
                var triggers = sec.triggers || [];
                for (var i = 0; i < triggers.length; i++) {
                    if (triggers[i].enabled === false) continue;
                    if (triggers[i].type == 'media' && triggers[i].duration_ticks) totalTicks += triggers[i].duration_ticks;
                    else if (triggers[i].type == 'pause' && triggers[i].duration > 0) totalTicks += triggers[i].duration * 10000000;
                }
            }
        }
        return SessionEditor.ticksToTime(totalTicks);
    }
};

// --- MODALE DE CALIBRAGE DES TOPS ---

var CalibrationModal = {
    sessionId: null,
    marks: {},
    videoEl: null,
    updateInterval: null,

    openFromEditor: function() {
        if (!SessionEditor.sessionData || !SessionEditor.sessionData.player_id) {
            bootbox.alert(_t('Veuillez d\'abord sélectionner un lecteur.'));
            return;
        }
        var filmSection = SessionEditor.sessionData.sections.film || { triggers: [] };
        var filmMedia = null;
        for (var i = 0; i < filmSection.triggers.length; i++) {
            if (filmSection.triggers[i].type == 'media') {
                filmMedia = filmSection.triggers[i];
                break;
            }
        }
        if (!filmMedia) {
            bootbox.alert(_t('Ajoutez d\'abord un film dans la section Film.'));
            return;
        }
        CalibrationModal.open(SessionEditor.eqLogicId, filmMedia.media_id, filmMedia.name, filmSection.marks || {});
    },

    open: function(sessionId, mediaId, mediaName, existingMarks) {
        CalibrationModal.sessionId = sessionId;
        CalibrationModal.marks = $.extend({}, existingMarks);

        // Récupérer l'URL de streaming
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'calibrate_start', id: sessionId, mediaId: mediaId },
            dataType: 'json',
            success: function(data) {
                if (data.state != 'ok') {
                    bootbox.alert(_t('Erreur: ') + (data.result || ''));
                    return;
                }
                CalibrationModal._openModal(mediaName, data.result.stream_url, existingMarks);
            }
        });
    },

    _openModal: function(mediaName, streamUrl, existingMarks) {
        var markLabels = SessionEditor.marksMeta ? SessionEditor.marksMeta.labels : {
            'pre_generique': 'Pré-générique', 'generique_1': 'Générique 1', 'post_film_1': 'Post film 1',
            'generique_2': 'Générique 2', 'post_film_2': 'Post film 2', 'fin': 'Fin'
        };
        var markOrder = SessionEditor.marksMeta ? SessionEditor.marksMeta.order : ['pre_generique','generique_1','post_film_1','generique_2','post_film_2','fin'];

        var marksHtml = '';
        for (var i = 0; i < markOrder.length; i++) {
            var mk = markOrder[i];
            var val = CalibrationModal.marks[mk];
            var valStr = (val !== null && val !== undefined) ? CalibrationModal.secondsToTime(val) + ' ✓' : '--:--';
            marksHtml += '<div style="display:flex; align-items:center; gap:10px; padding:6px 0; border-bottom:1px solid #333;">';
            marksHtml += '  <span style="flex-grow:1; color:#ccc;">' + markLabels[mk] + '</span>';
            marksHtml += '  <span id="mark-val-' + mk + '" style="color:#aaa; font-family:monospace; min-width:80px;">' + valStr + '</span>';
            marksHtml += '  <button class="btn btn-xs btn-primary" onclick="CalibrationModal.setMark(\'' + mk + '\')">Set</button>';
            marksHtml += '</div>';
        }

        var html = '<div style="display:flex; gap:15px; flex-wrap:wrap;">' +
            '<div style="flex:1; min-width:300px;">' +
            '  <video id="calib-video" style="width:100%; border-radius:6px; background:#000; max-height:40vh;" controls></video>' +
            '  <div style="text-align:center; margin-top:8px;">' +
            '    <span style="font-size:20px; font-family:monospace; color:#fff;" id="calib-position">00:00:00</span>' +
            '    <span style="font-size:12px; color:#666; font-family:monospace;" id="calib-total"> / --:--:--</span>' +
            '  </div>' +
            '  <div style="display:flex; justify-content:center; gap:8px; margin-top:10px;">' +
            '    <button class="btn btn-sm btn-default" onclick="CalibrationModal.seek(-10)"><i class="fas fa-backward"></i> -10s</button>' +
            '    <button class="btn btn-sm btn-default" onclick="CalibrationModal.seek(-1)"><i class="fas fa-step-backward"></i> -1s</button>' +
            '    <button class="btn btn-sm btn-default" onclick="CalibrationModal.togglePause()"><i class="fas fa-pause" id="calib-pause-icon"></i></button>' +
            '    <button class="btn btn-sm btn-default" onclick="CalibrationModal.seek(1)"><i class="fas fa-step-forward"></i> +1s</button>' +
            '    <button class="btn btn-sm btn-default" onclick="CalibrationModal.seek(10)"><i class="fas fa-forward"></i> +10s</button>' +
            '  </div>' +
            '</div>' +
            '<div style="flex:0 0 280px;">' +
            '  <div style="background:#1a1a1a; border-radius:4px; padding:10px;">' +
            '    <div style="color:#aaa; font-size:11px; text-transform:uppercase; font-weight:bold; margin-bottom:8px;"><i class="fas fa-crosshairs"></i> ' + _t('Marqueurs') + '</div>' +
            '    ' + marksHtml +
            '  </div>' +
            '</div>' +
            '</div>';

        bootbox.dialog({
            title: '<i class="fas fa-crosshairs"></i> ' + _t('Calibrage') + ' — ' + mediaName,
            message: html,
            className: 'jellyfin-modal-fullscreen',
            onEscape: function() { CalibrationModal.close(); },
            buttons: {
                cancel: { label: _t('Annuler'), className: 'btn-default', callback: function() { CalibrationModal.close(); } },
                save: {
                    label: _t('Valider'), className: 'btn-success',
                    callback: function() { CalibrationModal.saveAll(); }
                }
            }
        });

        // Initialiser le lecteur vidéo
        setTimeout(function() {
            CalibrationModal.videoEl = document.getElementById('calib-video');
            if (CalibrationModal.videoEl) {
                CalibrationModal.videoEl.src = streamUrl;
                CalibrationModal.videoEl.play().catch(function() {});

                // Mise à jour position en temps réel
                CalibrationModal.updateInterval = setInterval(function() {
                    var v = CalibrationModal.videoEl;
                    if (!v || !v.duration) return;
                    $('#calib-position').text(CalibrationModal.secondsToTime(Math.floor(v.currentTime)));
                    $('#calib-total').text('/ ' + CalibrationModal.secondsToTime(Math.floor(v.duration)));
                }, 200);
            }
        }, 500);
    },

    seek: function(delta) {
        if (!CalibrationModal.videoEl) return;
        CalibrationModal.videoEl.currentTime = Math.max(0, Math.min(CalibrationModal.videoEl.duration || 0, CalibrationModal.videoEl.currentTime + delta));
    },

    togglePause: function() {
        if (!CalibrationModal.videoEl) return;
        if (CalibrationModal.videoEl.paused) {
            CalibrationModal.videoEl.play();
            $('#calib-pause-icon').attr('class', 'fas fa-pause');
        } else {
            CalibrationModal.videoEl.pause();
            $('#calib-pause-icon').attr('class', 'fas fa-play');
        }
    },

    setMark: function(markName) {
        if (!CalibrationModal.videoEl) return;
        var seconds = Math.floor(CalibrationModal.videoEl.currentTime);
        CalibrationModal.marks[markName] = seconds;
        $('#mark-val-' + markName).text(CalibrationModal.secondsToTime(seconds) + ' ✓').css('color', '#1DB954');
    },

    saveAll: function() {
        var markOrder = SessionEditor.marksMeta ? SessionEditor.marksMeta.order : ['pre_generique','generique_1','post_film_1','generique_2','post_film_2','fin'];
        var pending = 0;
        for (var i = 0; i < markOrder.length; i++) {
            var mk = markOrder[i];
            if (CalibrationModal.marks[mk] !== null && CalibrationModal.marks[mk] !== undefined) {
                pending++;
                (function(markName) {
                    $.ajax({
                        type: 'POST',
                        url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                        data: { action: 'calibrate_set_mark', id: CalibrationModal.sessionId, mark_name: markName, position: CalibrationModal.marks[markName] },
                        dataType: 'json',
                        success: function() {
                            pending--;
                            if (pending <= 0) {
                                CalibrationModal.close();
                                SessionEditor.reload();
                            }
                        }
                    });
                })(mk);
            }
        }
        if (pending == 0) CalibrationModal.close();
    },

    close: function() {
        if (CalibrationModal.updateInterval) {
            clearInterval(CalibrationModal.updateInterval);
            CalibrationModal.updateInterval = null;
        }
        if (CalibrationModal.videoEl) {
            CalibrationModal.videoEl.pause();
            CalibrationModal.videoEl.src = '';
            CalibrationModal.videoEl = null;
        }
    },

    secondsToTime: function(sec) {
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = Math.floor(sec % 60);
        return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }
};

// --- CALIBRATION AUDIO ---

$('body').off('click', '.eqLogicAction[data-action=audio_calibration]').on('click', '.eqLogicAction[data-action=audio_calibration]', function() {
    AudioCalibration.open();
});

var AudioCalibration = {
    playerId: null,
    mediaId: null,
    mediaName: null,

    open: function() {
        var playersHtml = '<option value="">' + _t('Sélectionner...') + '</option>';
        // On récupère les lecteurs via AJAX all
        $.ajax({
            type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'all' }, dataType: 'json', global: false,
            success: function(data) {
                if (data.state != 'ok') return;
                data.result.forEach(function(eq) {
                    if (!eq.configuration || eq.configuration.session_type) return;
                    if (!eq.configuration.amp_volume_cmd_id) return;
                    var calibrated = (eq.configuration.audio_ref_volume && eq.configuration.audio_ref_lufs);
                    var badge = calibrated ? ' \u2713' : ' \u26A0';
                    playersHtml += '<option value="' + eq.id + '" data-ref-vol="' + (eq.configuration.audio_ref_volume || '') + '" data-ref-lufs="' + (eq.configuration.audio_ref_lufs || '') + '" data-ref-media="' + (eq.configuration.audio_ref_media_id || '') + '" data-has-info="' + (eq.configuration.amp_volume_info_cmd_id ? '1' : '0') + '">' + eq.name + badge + '</option>';
                });

                var html = '<div style="padding:10px;">' +
                    '<div class="form-group"><label>' + _t('Lecteur') + '</label>' +
                    '<select id="calib-player" class="form-control">' + playersHtml + '</select>' +
                    '<div id="calib-status" style="margin-top:5px; font-size:11px; color:#888;"></div></div>' +
                    '<hr>' +
                    '<div class="form-group"><label>1. ' + _t('Média de référence') + '</label>' +
                    '<div style="background:#1a1a1a; padding:8px; border-radius:4px; margin-bottom:8px;">' +
                    '<div style="font-size:11px; color:#888; margin-bottom:5px;"><i class="fas fa-info-circle"></i> ' + _t('Utilisez le bruit rose de référence (-24 LUFS, standard broadcast). Téléchargez-le et importez-le dans Jellyfin (une seule fois).') + '</div>' +
                    '<button class="btn btn-xs btn-info" onclick="window.open(\'plugins/jellyfin/core/php/download.php?file=reference_pink_noise_-24LUFS.wav\', \'_blank\')"><i class="fas fa-download"></i> ' + _t('Télécharger le bruit rose') + '</button>' +
                    '</div>' +
                    '<div id="calib-media-display" style="color:#aaa; font-size:12px;">' + _t('Aucun') + '</div>' +
                    '<button class="btn btn-xs btn-primary" id="calib-pick-media"><i class="fas fa-film"></i> ' + _t('Sélectionner dans Jellyfin') + '</button></div>' +
                    '<div class="form-group"><label>2. ' + _t('Écoute') + '</label><br>' +
                    '<button class="btn btn-xs btn-success" id="calib-play"><i class="fas fa-play"></i> ' + _t('Lire en boucle') + '</button> ' +
                    '<button class="btn btn-xs btn-danger" id="calib-stop"><i class="fas fa-stop"></i> ' + _t('Arrêter') + '</button>' +
                    '<div style="font-size:11px; color:#666; margin-top:4px;">' + _t('Réglez votre ampli au volume idéal.') + '</div></div>' +
                    '<div class="form-group"><label>3. ' + _t('Volume de référence') + '</label><br>' +
                    '<button class="btn btn-xs btn-default" id="calib-capture"><i class="fas fa-satellite-dish"></i> ' + _t('Capturer') + '</button> ' +
                    '<input type="number" id="calib-volume" class="form-control" style="width:100px; display:inline-block;" min="0" max="100" placeholder="50" /></div>' +
                    '<div class="form-group"><label>4. ' + _t('Analyse LUFS') + '</label><br>' +
                    '<button class="btn btn-xs btn-default" id="calib-analyze"><i class="fas fa-search"></i> ' + _t('Analyser') + '</button> ' +
                    '<span id="calib-lufs" style="font-family:monospace; color:#aaa;">--</span></div>' +
                    '</div>';

                var modal = bootbox.dialog({
                    title: '<i class="fas fa-volume-up"></i> ' + _t('Calibration Audio'),
                    message: html,
                    size: 'large',
                    buttons: {
                        cancel: { label: _t('Annuler'), className: 'btn-default' },
                        save: { label: '<i class="fas fa-save"></i> ' + _t('Sauvegarder'), className: 'btn-success', callback: function() {
                            AudioCalibration.save();
                        }}
                    }
                });

                // Events
                $('#calib-player').on('change', function() {
                    var $opt = $(this).find(':selected');
                    AudioCalibration.playerId = $(this).val();
                    var refVol = $opt.data('ref-vol');
                    var refLufs = $opt.data('ref-lufs');
                    var hasInfo = $opt.data('has-info') == '1';
                    if (refVol && refLufs) {
                        $('#calib-status').html('<span style="color:#1DB954;">\u2713 ' + _t('Calibré') + ' (vol: ' + refVol + ', LUFS: ' + refLufs + ')</span>');
                        $('#calib-volume').val(refVol);
                        $('#calib-lufs').text(refLufs);
                    } else {
                        $('#calib-status').html('<span style="color:#f39c12;">\u26A0 ' + _t('Non calibré') + '</span>');
                    }
                    $('#calib-capture').prop('disabled', !hasInfo);
                });

                $('#calib-pick-media').on('click', function() {
                    var $calibModal = modal;
                    $calibModal.css('z-index', 0);
                    if (typeof JellyfinBrowser !== 'undefined') {
                        JellyfinBrowser.open(AudioCalibration.playerId || '', '');
                        setTimeout(function() {
                            var $btn = $('.jellyfin-modal-fullscreen .btn-success');
                            $btn.off('click').html('<i class="fas fa-check"></i> ' + _t('Sélectionner'));
                            $btn.on('click', function() {
                                if (JellyfinBrowser.selectedItem) {
                                    AudioCalibration.mediaId = JellyfinBrowser.selectedItem.Id;
                                    AudioCalibration.mediaName = JellyfinBrowser.selectedItem.Name;
                                }
                                // Fermer SEULEMENT le JellyfinBrowser, pas la calibration
                                $('.jellyfin-modal-fullscreen').modal('hide');
                                // Restaurer la modale de calibration
                                $calibModal.css('z-index', '');
                                // Mettre à jour l'affichage du média
                                if (AudioCalibration.mediaId) {
                                    $('#calib-media-display').html('<i class="fas fa-film" style="color:#3498db;"></i> ' + AudioCalibration.mediaName);
                                }
                                return false;
                            });
                        }, 500);
                    }
                });

                $('#calib-play').on('click', function() {
                    if (!AudioCalibration.playerId || !AudioCalibration.mediaId) { bootbox.alert(_t('Sélectionnez un lecteur et un média.')); return; }
                    $.ajax({
                        type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                        data: { action: 'play_media', id: AudioCalibration.playerId, mediaId: AudioCalibration.mediaId, mode: 'play_now' },
                        dataType: 'json'
                    });
                });

                $('#calib-stop').on('click', function() {
                    if (!AudioCalibration.playerId) return;
                    // Récupérer la commande stop du lecteur et l'exécuter
                    $.ajax({
                        type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                        data: { action: 'get_player_cmd_ids', player_id: AudioCalibration.playerId },
                        dataType: 'json', global: false,
                        success: function(data) {
                            if (data.state == 'ok' && data.result.stop) {
                                $.ajax({
                                    type: 'POST', url: 'core/ajax/cmd.ajax.php',
                                    data: { action: 'execCmd', id: data.result.stop },
                                    dataType: 'json', global: false
                                });
                            }
                        }
                    });
                });

                $('#calib-capture').on('click', function() {
                    if (!AudioCalibration.playerId) return;
                    $(this).html('<i class="fas fa-spinner fa-spin"></i>');
                    var btn = $(this);
                    $.ajax({
                        type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                        data: { action: 'capture_amp_volume', player_id: AudioCalibration.playerId },
                        dataType: 'json',
                        success: function(data) {
                            btn.html('<i class="fas fa-satellite-dish"></i> ' + _t('Capturer'));
                            if (data.state == 'ok') {
                                $('#calib-volume').val(data.result.volume);
                            } else {
                                bootbox.alert(data.result);
                            }
                        }
                    });
                });

                $('#calib-analyze').on('click', function() {
                    if (!AudioCalibration.mediaId) { bootbox.alert(_t('Sélectionnez un média.')); return; }
                    $(this).html('<i class="fas fa-spinner fa-spin"></i> ' + _t('Analyse...'));
                    var btn = $(this);
                    $.ajax({
                        type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                        data: { action: 'analyze_lufs', mediaId: AudioCalibration.mediaId, mode: 'complete' },
                        dataType: 'json', timeout: 120000,
                        success: function(data) {
                            btn.html('<i class="fas fa-search"></i> ' + _t('Analyser'));
                            if (data.state == 'ok') {
                                $('#calib-lufs').text(data.result.lufs.toFixed(1) + ' LUFS').css('color', '#1DB954');
                            } else {
                                $('#calib-lufs').text(_t('Erreur')).css('color', '#e74c3c');
                            }
                        }
                    });
                });

                // Restore state si on revient après pick media
                if (AudioCalibration.mediaId) {
                    $('#calib-media-display').html('<i class="fas fa-film" style="color:#3498db;"></i> ' + AudioCalibration.mediaName);
                }
                if (AudioCalibration.playerId) {
                    $('#calib-player').val(AudioCalibration.playerId).change();
                }
            }
        });
    },

    save: function() {
        var volume = $('#calib-volume').val();
        var lufsText = $('#calib-lufs').text();
        var lufs = parseFloat(lufsText);
        if (!AudioCalibration.playerId || !volume || isNaN(lufs)) {
            bootbox.alert(_t('Veuillez compléter toutes les étapes.'));
            return false;
        }
        $.ajax({
            type: 'POST', url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: {
                action: 'save_calibration',
                player_id: AudioCalibration.playerId,
                ref_volume: volume,
                ref_lufs: lufs,
                ref_media_id: AudioCalibration.mediaId || ''
            },
            dataType: 'json',
            success: function(data) {
                if (data.state == 'ok') {
                    $('#div_alert').showAlert({ message: _t('Calibration sauvegardée !'), level: 'success' });
                }
            }
        });
    }
};