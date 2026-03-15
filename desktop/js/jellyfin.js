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
                cardHtml = `<div class="jelly-card jelly-media" id="card-${item.Id}" onclick="JellyfinBrowser.selectMedia('${item.Id}', '${title.replace(/'/g, "\\'")}', '${imgTag}', '${year}', '${rating}', '${overview}', '${imageUrl}', '${duration}', '${videoRes}', '${audioInfo}')"><div class="jelly-img-container">${imgContent}</div><div class="jelly-title">${title}</div></div>`;
            }
            container.append(cardHtml);
        });
    },

    // MODIF : Ajout des arguments videoRes et audioInfo
    selectMedia: function (itemId, title, imgTag, year, rating, overview, imgUrl, duration, videoRes, audioInfo) {
        $('.jelly-card').removeClass('selected');
        $('#card-' + itemId).addClass('selected');
        JellyfinBrowser.selectedItem = {Id: itemId, Name: title, ImgTag: imgTag};
        
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

        for (var i = 0; i < order.length; i++) {
            var key = order[i];
            var section = SessionEditor.sessionData.sections[key] || { triggers: [] };
            var triggers = section.triggers || [];
            var color = colors[key] || '#888';
            var label = labels[key] || key;
            var dur = SessionEditor.calculateDuration(triggers);
            var count = triggers.length;

            html += '<div class="session-section" data-section="' + key + '">';
            html += '  <div class="session-section-header" onclick="SessionEditor.toggleSection(\'' + key + '\')" style="cursor:pointer; background:#2a2a2a; padding:10px 12px; border:1px solid #333; border-radius:4px; margin-bottom:2px; display:flex; align-items:center; gap:10px;">';
            html += '    <span style="color:' + color + '; font-size:16px;">●</span>';
            html += '    <span style="color:' + color + '; font-weight:bold; flex-grow:1;">' + label + '</span>';
            html += '    <span style="color:#888; font-size:12px;">' + count + ' ' + _t('élément(s)') + '</span>';
            html += '    <span style="color:#aaa; font-size:12px; font-family:monospace;">' + dur + '</span>';
            html += '    <i class="fas fa-chevron-right session-section-chevron" style="color:#666; transition:transform 0.2s;"></i>';
            html += '  </div>';
            html += '  <div class="session-section-body" data-section="' + key + '" style="display:none; border:1px solid #333; border-top:none; border-radius:0 0 4px 4px; padding:10px; margin-bottom:8px; background:#222;">';
            html += SessionEditor.renderTriggerList(triggers, key);
            html += '    <div style="margin-top:8px; display:flex; gap:6px;">';
            html += '      <button class="btn btn-xs btn-primary" onclick="SessionEditor.addMedia(\'' + key + '\')"><i class="fas fa-film"></i> ' + _t('Média') + '</button>';
            html += '      <button class="btn btn-xs btn-warning" onclick="SessionEditor.addPause(\'' + key + '\')"><i class="fas fa-pause"></i> ' + _t('Pause') + '</button>';
            html += '      <button class="btn btn-xs btn-danger" onclick="SessionEditor.addAction(\'' + key + '\')"><i class="fas fa-bolt"></i> ' + _t('Action') + '</button>';
            html += '    </div>';
            html += '  </div>';
            html += '</div>';
        }

        html += '<div style="margin-top:15px; padding:10px; background:#1a1a1a; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">';
        html += '  <span style="color:#aaa; font-size:13px;"><i class="fas fa-clock"></i> ' + _t('Durée totale estimée') + ' : <strong style="color:#fff;" id="session-total-duration">' + SessionEditor.calculateTotalDuration() + '</strong></span>';
        html += '  <div style="display:flex; gap:6px;">';
        html += '    <button class="btn btn-sm btn-success" onclick="SessionEditor.startSession()"><i class="fas fa-play"></i> ' + _t('Lancer') + '</button>';
        html += '    <button class="btn btn-sm btn-default" onclick="CalibrationModal.openFromEditor()"><i class="fas fa-crosshairs"></i> ' + _t('Calibrer tops') + '</button>';
        html += '  </div>';
        html += '</div>';

        $('#session-editor-container').html(html);
    },

    renderCommercial: function() {
        var playlist = SessionEditor.sessionData.playlist || [];
        var dur = SessionEditor.calculateDuration(playlist);

        var html = '<div class="session-section" data-section="playlist">';
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

        html += '<div style="margin-top:15px; padding:10px; background:#1a1a1a; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">';
        html += '  <span style="color:#aaa; font-size:13px;"><i class="fas fa-clock"></i> ' + _t('Durée totale') + ' : <strong style="color:#fff;" id="session-total-duration">' + dur + '</strong> · <i class="fas fa-redo"></i> ' + _t('Boucle infinie') + '</span>';
        html += '  <button class="btn btn-sm btn-success" onclick="SessionEditor.startSession()"><i class="fas fa-play"></i> ' + _t('Lancer') + '</button>';
        html += '</div>';

        $('#session-editor-container').html(html);
    },

    renderTriggerList: function(triggers, sectionKey) {
        if (!triggers || triggers.length == 0) {
            return '<div style="color:#555; font-size:12px; padding:5px; text-align:center;">' + _t('Aucun élément') + '</div>';
        }
        var html = '';
        for (var i = 0; i < triggers.length; i++) {
            var t = triggers[i];
            var icon = '', label = '', durStr = '';
            if (t.type == 'media') {
                icon = '<i class="fas fa-film" style="color:#3498db;"></i>';
                label = t.name || t.media_id;
                if (t.duration_ticks) durStr = SessionEditor.ticksToTime(t.duration_ticks);
            } else if (t.type == 'pause') {
                icon = '<i class="fas fa-pause-circle" style="color:#f39c12;"></i>';
                label = t.duration > 0 ? _t('Pause') + ' ' + t.duration + 's' : _t('Pause illimitée');
            } else if (t.type == 'command') {
                icon = '<i class="fas fa-bolt" style="color:#e74c3c;"></i>';
                label = t.label || (_t('Commande') + ' #' + t.cmd_id);
            } else if (t.type == 'scenario') {
                icon = '<i class="fas fa-cogs" style="color:#9b59b6;"></i>';
                label = t.label || (_t('Scénario') + ' #' + t.scenario_id);
            }
            html += '<div style="background:#1a1a1a; padding:6px 10px; border-radius:3px; margin-bottom:3px; display:flex; align-items:center; gap:8px; font-size:12px; color:#ccc;">';
            html += '  ' + icon + ' <span style="flex-grow:1;">' + label + '</span>';
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
                        duration_ticks: item.RunTimeTicks || 0
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

    toggleSection: function(sectionKey) {
        var $body = $('.session-section-body[data-section="' + sectionKey + '"]');
        var $chevron = $body.prev().find('.session-section-chevron');
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
                if (pl[i].duration_ticks) totalTicks += pl[i].duration_ticks;
            }
        } else {
            var order = SessionEditor.sectionsMeta.order;
            for (var s = 0; s < order.length; s++) {
                var triggers = (SessionEditor.sessionData.sections[order[s]] || {}).triggers || [];
                for (var i = 0; i < triggers.length; i++) {
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
    playerId: null,
    marks: {},
    pollInterval: null,

    openFromEditor: function() {
        if (!SessionEditor.sessionData || !SessionEditor.sessionData.player_id) {
            bootbox.alert(_t('Veuillez d\'abord sélectionner un lecteur.'));
            return;
        }
        // Trouver le premier média de la section film
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
        CalibrationModal.open(SessionEditor.eqLogicId, filmMedia.media_id, filmMedia.name, SessionEditor.sessionData.player_id, filmSection.marks || {});
    },

    open: function(sessionId, mediaId, mediaName, playerId, existingMarks) {
        CalibrationModal.sessionId = sessionId;
        CalibrationModal.playerId = playerId;
        CalibrationModal.marks = $.extend({}, existingMarks);

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

        var html = '<div style="text-align:center; margin-bottom:15px;">' +
            '<div style="font-size:24px; font-family:monospace; color:#fff;" id="calib-position">00:00:00</div>' +
            '<div style="margin:10px 0; height:12px; background:#333; border-radius:6px; cursor:pointer; position:relative;" id="calib-progress-bar">' +
            '  <div id="calib-progress-fill" style="height:100%; background:#1DB954; border-radius:6px; width:0%; transition:width 0.3s;"></div>' +
            '</div>' +
            '<div style="display:flex; justify-content:center; gap:8px; margin-bottom:15px;">' +
            '  <button class="btn btn-sm btn-default" onclick="CalibrationModal.seek(-10)"><i class="fas fa-backward"></i> -10s</button>' +
            '  <button class="btn btn-sm btn-default" onclick="CalibrationModal.seek(-1)"><i class="fas fa-step-backward"></i> -1s</button>' +
            '  <button class="btn btn-sm btn-default" onclick="CalibrationModal.togglePause()"><i class="fas fa-pause" id="calib-pause-icon"></i></button>' +
            '  <button class="btn btn-sm btn-default" onclick="CalibrationModal.seek(1)"><i class="fas fa-step-forward"></i> +1s</button>' +
            '  <button class="btn btn-sm btn-default" onclick="CalibrationModal.seek(10)"><i class="fas fa-forward"></i> +10s</button>' +
            '</div></div>' +
            '<div style="background:#1a1a1a; border-radius:4px; padding:10px;">' + marksHtml + '</div>';

        bootbox.dialog({
            title: '<i class="fas fa-crosshairs"></i> ' + _t('Calibrage') + ' — ' + mediaName,
            message: html,
            className: 'jellyfin-modal-fullscreen',
            buttons: {
                cancel: { label: _t('Annuler'), className: 'btn-default', callback: function() { CalibrationModal.close(); } },
                save: {
                    label: _t('Valider'), className: 'btn-success',
                    callback: function() { CalibrationModal.saveAll(); }
                }
            }
        });

        // Seek sur clic barre
        setTimeout(function() {
            $('#calib-progress-bar').on('click', function(e) {
                var pct = (e.pageX - $(this).offset().left) / $(this).width();
                CalibrationModal.seekTo(pct);
            });
        }, 300);

        // Lancer la lecture
        $.ajax({
            type: 'POST',
            url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
            data: { action: 'calibrate_start', id: sessionId, mediaId: mediaId },
            dataType: 'json',
            success: function(data) {
                if (data.state != 'ok') bootbox.alert(_t('Erreur: ') + (data.result || ''));
            }
        });

        // Polling position
        CalibrationModal.pollInterval = setInterval(CalibrationModal.updatePosition, 500);
    },

    updatePosition: function() {
        // Lire position depuis les commandes du lecteur
        var $playerWidget = $('.eqLogic[data-eqLogic_id=' + CalibrationModal.playerId + ']');
        if ($playerWidget.length == 0) return;
        var posText = $playerWidget.find('.time-current').text();
        var totalText = $playerWidget.find('.time-total').text();
        $('#calib-position').text(posText || '00:00:00');
        // Barre de progression
        var posSec = CalibrationModal.timeToSeconds(posText);
        var totalSec = CalibrationModal.timeToSeconds(totalText);
        if (totalSec > 0) {
            $('#calib-progress-fill').css('width', (posSec / totalSec * 100) + '%');
        }
    },

    seek: function(delta) {
        var posText = $('#calib-position').text();
        var current = CalibrationModal.timeToSeconds(posText);
        var newPos = Math.max(0, current + delta);
        jeedom.cmd.execute({
            id: CalibrationModal.getPlayerCmdId('set_position'),
            value: { slider: newPos }
        });
    },

    seekTo: function(pct) {
        var $playerWidget = $('.eqLogic[data-eqLogic_id=' + CalibrationModal.playerId + ']');
        var totalText = $playerWidget.find('.time-total').text();
        var totalSec = CalibrationModal.timeToSeconds(totalText);
        if (totalSec <= 0) return;
        var newPos = Math.floor(pct * totalSec);
        jeedom.cmd.execute({
            id: CalibrationModal.getPlayerCmdId('set_position'),
            value: { slider: newPos }
        });
    },

    togglePause: function() {
        var cmdId = CalibrationModal.getPlayerCmdId('play_pause');
        if (cmdId) jeedom.cmd.execute({ id: cmdId });
    },

    setMark: function(markName) {
        var posText = $('#calib-position').text();
        var seconds = CalibrationModal.timeToSeconds(posText);
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
                $.ajax({
                    type: 'POST',
                    url: 'plugins/jellyfin/core/ajax/jellyfin.ajax.php',
                    data: { action: 'calibrate_set_mark', id: CalibrationModal.sessionId, mark_name: mk, position: CalibrationModal.marks[mk] },
                    dataType: 'json',
                    success: function() {
                        pending--;
                        if (pending <= 0) {
                            CalibrationModal.close();
                            SessionEditor.reload();
                        }
                    }
                });
            }
        }
        if (pending == 0) CalibrationModal.close();
    },

    close: function() {
        if (CalibrationModal.pollInterval) {
            clearInterval(CalibrationModal.pollInterval);
            CalibrationModal.pollInterval = null;
        }
    },

    getPlayerCmdId: function(logicalId) {
        var cmdId = null;
        // Chercher l'ID commande via le DOM du widget
        var $widget = $('.eqLogic[data-eqLogic_id=' + CalibrationModal.playerId + ']');
        if (logicalId == 'set_position') {
            var onclick = $widget.find('.progress-area').attr('data-set-position-id');
            if (onclick) return onclick;
        }
        // Fallback: chercher dans les cmd-widget
        $widget.find('.cmd[data-cmd_id]').each(function() {
            // On ne peut pas facilement trouver le logicalId côté DOM
            // On utilise l'id passé dans le template
        });
        return null;
    },

    timeToSeconds: function(str) {
        if (!str || str.indexOf('--') !== -1) return 0;
        str = String(str).replace(/<[^>]*>?/gm, '').trim();
        var p = str.split(':'), s = 0, m = 1;
        while (p.length > 0) { s += m * parseInt(p.pop(), 10); m *= 60; }
        return isNaN(s) ? 0 : s;
    },

    secondsToTime: function(sec) {
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = Math.floor(sec % 60);
        return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }
};