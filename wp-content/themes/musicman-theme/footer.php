  </div><!-- .main-content -->

  <!-- Right Panel -->
  <div class="right-properties" id="rightPanel">
    <div id="rightPanelContent">
        <div class="props-card"><div class="empty-msg"><i class="fas fa-info-circle"></i> No node selected.</div></div>
    </div>
  </div>
</div><!-- .pmah-layout -->

<!-- ===== AUDIO PLAYER ===== -->
<div class="audio-player-bar" id="audioPlayerBar">
  <div class="ap-artwork" id="apArtwork"><i class="fas fa-music"></i></div>
  <div class="ap-info">
    <div class="ap-title" id="apTitle">No track loaded</div>
    <div class="ap-artist" id="apArtist">—</div>
  </div>
  <div class="ap-controls">
    <button id="apPrevBtn" title="Previous"><i class="fas fa-step-backward"></i></button>
    <button class="ap-play-btn" id="apPlayBtn" title="Play / Pause"><i class="fas fa-play"></i></button>
    <button id="apNextBtn" title="Next"><i class="fas fa-step-forward"></i></button>
  </div>
  <div class="ap-progress-wrap">
    <span class="ap-time" id="apCurrentTime">0:00</span>
    <input type="range" id="apProgress" min="0" max="1000" value="0" />
    <span class="ap-time" id="apDuration">0:00</span>
  </div>
  <div class="ap-volume-wrap">
    <i class="fas fa-volume-up"></i>
    <input type="range" id="apVolume" min="0" max="100" value="80" />
  </div>
  <button class="ap-close-btn" id="apCloseBtn" title="Close player"><i class="fas fa-times"></i></button>
</div>

<!-- Toast -->
<div id="toast" class="toast-message"></div>

<?php wp_footer(); ?>
</body>
</html>
