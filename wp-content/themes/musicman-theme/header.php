<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<!-- Mobile Header -->
<div class="mobile-header-bar">
  <button class="menu-toggle-btn" id="menuToggle"><i class="fas fa-bars"></i></button>
  <span class="header-title"><?php bloginfo( 'name' ); ?></span>
  <button class="user-toggle-btn" id="userToggle"><i class="fas fa-user-circle"></i></button>
  <button class="props-toggle-btn" id="propsToggle"><i class="fas fa-info-circle"></i></button>
</div>

<div class="layout-overlay" id="layoutOverlay"></div>

<div class="pmah-layout">
  <!-- Left Nav -->
  <div class="left-nav" id="leftSidebar">
    <div class="left-header">
      <i class="fas fa-music"></i>
      <span>MusicMan</span>
      <span class="badge" id="treeTotalCount">0 items</span>
      <span class="user-info" id="userInfo">
        <i class="fas fa-user"></i>
        <span id="userName"><?php echo is_user_logged_in() ? wp_get_current_user()->display_name : 'Guest'; ?></span>
      </span>
    </div>
    <div class="search-panel">
      <input type="text" id="searchTerm" placeholder="Search criteria..." value="Pink Floyd" />
      <div class="search-row">
        <select id="entityType">
          <option value="musicArtist,album,song">All Types</option>
          <option value="musicArtist">Artists</option>
          <option value="album">Albums</option>
          <option value="song">Tracks</option>
        </select>
        <select id="resultFilter">
          <option value="all">All Results</option>
          <option value="artist">Artists Only</option>
          <option value="collection">Albums Only</option>
          <option value="track">Tracks Only</option>
        </select>
      </div>
      <button id="doSearchBtn"><i class="fas fa-search"></i> Query Database</button>
    </div>
    <div class="tree-container" id="treeContainer">
      <div class="empty-msg"><i class="fas fa-search"></i> Execute query above to construct tree nodes.</div>
    </div>
    <div id="treeBulkBar" class="bulk-bar" style="display:none;">
      <i class="fas fa-check-square"></i> <span id="treeSelectedCount">0</span> selected
      <button id="treeBulkAddBtn" class="btn-sm"><i class="fas fa-plus"></i> Import</button>
      <button id="treeSelectAllBtn" class="btn-sm btn-primary"><i class="fas fa-check-double"></i> All</button>
      <button id="treeSelectNoneBtn" class="btn-sm"><i class="fas fa-times"></i> Clear</button>
    </div>
  </div>

  <!-- Main Content Area -->
  <div class="main-content">
    <div class="tabs">
      <div class="tab <?php echo is_front_page() ? 'active' : ''; ?>" onclick="window.location.href='<?php echo home_url('/'); ?>'">
        <i class="fas fa-home"></i> Home
      </div>
      <div class="tab <?php echo is_page('queue') ? 'active' : ''; ?>" onclick="window.location.href='<?php echo home_url('/queue'); ?>'">
        <i class="fas fa-tasks"></i> Queue
      </div>
    </div>
