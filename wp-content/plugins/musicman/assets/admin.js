jQuery(document).ready(function($) {

    // Helper: show modal, hide on complete
    function showModal(message) {
        $('#mt-import-message').text(message || 'Importing…');
        $('#mt-import-modal').show();
    }
    function hideModal() {
        $('#mt-import-modal').hide();
    }

    // ------------------ TRACK ADMIN ------------------
    if (mt_admin.post_type === 'musicman_track') {
        var $searchInput   = $('#mt-search-input');
        var $searchBtn     = $('#mt-search-btn');
        var $results       = $('#mt-search-results');
        var $spinner       = $('#mt-search-spinner');
        var $lyricsBtn     = $('#mt-fetch-lyrics-btn');
        var $lyricsStatus  = $('#mt-lyrics-status');

        $searchBtn.on('click', function() {
            var term = $searchInput.val().trim();
            if (!term) return;
            $spinner.addClass('is-active').show();
            $results.hide().empty();
            $.post(mt_admin.ajaxurl, {
                action: 'mt_search_itunes_track',
                term: term,
                nonce: mt_admin.nonce
            }, function(resp) {
                $spinner.removeClass('is-active').hide();
                if (resp && resp !== '0') {
                    $results.html(resp).show();
                } else {
                    $results.html('<p style="padding:10px;">No results.</p>').show();
                }
            });
        });

        $results.on('click', 'li', function() {
            var track = $(this).data('track');
            if (!track) return;
            $.each(track, function(key, value) {
                var $field = $('input[name="mt_' + key + '"], textarea[name="mt_' + key + '"]');
                if ($field.length) $field.val(typeof value === 'object' ? JSON.stringify(value, null, 2) : value);
            });
            if (track.trackName && track.artistName) {
                $('#title').val(track.trackName + ' – ' + track.artistName);
                $('#title-prompt-text').addClass('screen-reader-text');
            }
            $results.hide();
        });

        $searchInput.on('keypress', function(e) { if (e.which === 13) $searchBtn.click(); });

        $lyricsBtn.on('click', function() {
            $lyricsStatus.text('Fetching...');
            $.post(mt_admin.ajaxurl, {
                action: 'mt_fetch_lyrics',
                post_id: mt_admin.post_id,
                nonce: mt_admin.nonce
            }, function(resp) {
                if (resp === '0') { $lyricsStatus.text('No lyrics found.'); return; }
                try {
                    var data = JSON.parse(resp);
                    if (data.plain || data.synced) {
                        $('textarea[name="mt_lyricsPlain"]').val(data.plain || '');
                        $('textarea[name="mt_lyricsSynced"]').val(data.synced || '');
                        $('input[name="mt_lyricsFetched"]').val(new Date().toISOString().replace('T',' ').slice(0,19));
                        $lyricsStatus.text('Fetched.');
                    }
                } catch(e) { $lyricsStatus.text('Error.'); }
            });
        });

        $('#mt-like-toggle').on('click', function() {
            var $btn = $(this);
            $.post(mt_admin.ajaxurl, {
                action: 'mt_toggle_like',
                post_id: $btn.data('post'),
                nonce: mt_admin.nonce
            }, function(resp) {
                if (resp && resp.likes !== undefined) {
                    $btn.closest('.inside').find('p:nth-child(2)').html('<strong>Likes:</strong> ' + resp.likes);
                }
            });
        });

        $('#mt-trigger-import').on('click', function() {
            if ($(this).prop('disabled')) return;
            showModal('Creating artist & album…');
            $.post(mt_admin.ajaxurl, {
                action: 'mt_crawl_track',
                post_id: mt_admin.post_id,
                nonce: mt_admin.nonce
            }, function(response) {
                hideModal();
                if (response.success) {
                    $('#mt-import-status').text('Imported');
                    $('#mt-trigger-import').text('Re‑import Artist & Album');
                } else {
                    alert('Import failed.');
                }
            });
        });
    }

    // ------------------ ARTIST ADMIN ------------------
    if (mt_admin.post_type === 'musicman_artist') {
        var $artSearchInput = $('#mt-artist-search-input');
        var $artSearchBtn   = $('#mt-artist-search-btn');
        var $artResults     = $('#mt-artist-results');
        var $artSpinner     = $('#mt-artist-spinner');

        $artSearchBtn.on('click', function() {
            var term = $artSearchInput.val().trim();
            if (!term) return;
            $artSpinner.addClass('is-active').show();
            $artResults.hide().empty();
            $.post(mt_admin.ajaxurl, {
                action: 'mt_search_itunes_artist',
                term: term,
                nonce: mt_admin.nonce
            }, function(resp) {
                $artSpinner.removeClass('is-active').hide();
                if (resp && resp !== '0') {
                    $artResults.html(resp).show();
                } else {
                    $artResults.html('<p style="padding:10px;">No results.</p>').show();
                }
            });
        });

        $artResults.on('click', 'li', function() {
            var artist = $(this).data('artist');
            if (!artist) return;
            $.each(artist, function(key, value) {
                var $field = $('input[name="mt_' + key + '"]');
                if ($field.length) $field.val(value);
            });
            if (artist.artistName) {
                $('#title').val(artist.artistName);
                $('#title-prompt-text').addClass('screen-reader-text');
            }
            $artResults.hide();
        });

        $artSearchInput.on('keypress', function(e) { if (e.which === 13) $artSearchBtn.click(); });

        $('#mt-trigger-import').on('click', function() {
            if ($(this).prop('disabled')) return;
            showModal('Importing full discography…');
            $.post(mt_admin.ajaxurl, {
                action: 'mt_crawl_artist',
                post_id: mt_admin.post_id,
                nonce: mt_admin.nonce
            }, function(response) {
                hideModal();
                if (response.success) {
                    $('#mt-import-status').text('Imported');
                    $('#mt-trigger-import').text('Re‑import Discography');
                } else {
                    alert('Import failed.');
                }
            });
        });
    }

    // ------------------ COLLECTION ADMIN ------------------
    if (mt_admin.post_type === 'musicman_collection') {
        var $collSearchInput = $('#mt-album-search-input');
        var $collSearchBtn   = $('#mt-album-search-btn');
        var $collResults     = $('#mt-album-results');
        var $collSpinner     = $('#mt-album-spinner');

        $collSearchBtn.on('click', function() {
            var term = $collSearchInput.val().trim();
            if (!term) return;
            $collSpinner.addClass('is-active').show();
            $collResults.hide().empty();
            $.post(mt_admin.ajaxurl, {
                action: 'mt_search_itunes_album',
                term: term,
                nonce: mt_admin.nonce
            }, function(resp) {
                $collSpinner.removeClass('is-active').hide();
                if (resp && resp !== '0') {
                    $collResults.html(resp).show();
                } else {
                    $collResults.html('<p style="padding:10px;">No results.</p>').show();
                }
            });
        });

        $collResults.on('click', 'li', function() {
            var album = $(this).data('album');
            if (!album) return;
            $.each(album, function(key, value) {
                var $field = $('input[name="mt_' + key + '"]');
                if ($field.length) $field.val(value);
            });
            if (album.collectionName) {
                $('#title').val(album.collectionName);
                $('#title-prompt-text').addClass('screen-reader-text');
            }
            $collResults.hide();
        });

        $collSearchInput.on('keypress', function(e) { if (e.which === 13) $collSearchBtn.click(); });

        $('#mt-trigger-import').on('click', function() {
            if ($(this).prop('disabled')) return;
            showModal('Importing tracks…');
            $.post(mt_admin.ajaxurl, {
                action: 'mt_crawl_collection',
                post_id: mt_admin.post_id,
                nonce: mt_admin.nonce
            }, function(response) {
                hideModal();
                if (response.success) {
                    $('#mt-import-status').text('Imported');
                    $('#mt-trigger-import').text('Re‑import Tracks');
                } else {
                    alert('Import failed.');
                }
            });
        });
    }

});
