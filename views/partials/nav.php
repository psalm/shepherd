<?php

/**
 * Skip link and site header with the theme toggle (the toggle is revealed by assets/js/theme.js).
 *
 * @return Closure(): void
 */
return static function () : void {
    ?>
<a class="skip-link" href="#main">Skip to main content</a>
<header class="site-header">
    <nav class="site-nav" aria-label="Main">
        <div class="container site-nav-inner">
            <p class="site-title"><a href="/">Shepherd</a></p>
            <button type="button" class="theme-toggle" data-theme-toggle>
                <svg class="theme-icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                    <g class="theme-icon-system"><circle cx="8" cy="8" r="6.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 1.75a6.25 6.25 0 0 0 0 12.5z" fill="currentColor"/></g>
                    <g class="theme-icon-light"><circle cx="8" cy="8" r="3" fill="currentColor"/><path d="M8 .75v2M8 13.25v2M.75 8h2M13.25 8h2M2.9 2.9l1.4 1.4M11.7 11.7l1.4 1.4M2.9 13.1l1.4-1.4M11.7 4.3l1.4-1.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></g>
                    <path class="theme-icon-dark" d="M13.5 9.6A5.9 5.9 0 0 1 6.4 2.5a5.9 5.9 0 1 0 7.1 7.1z" fill="currentColor"/>
                </svg>
                <span class="theme-toggle-text">Theme: <span data-theme-label>System</span></span>
            </button>
            <span class="visually-hidden" role="status" data-theme-status></span>
        </div>
    </nav>
</header>
    <?php
};
