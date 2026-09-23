<?php
/**
 * APX · 统一图标精灵（SVG sprite）
 * 全部图标为 24×24 描边风格、currentColor 着色，随主题自适应（黑白主题下即纯黑白）。
 * 用法：在任意视图调用 icon('home') 或 <svg class="apx-ico"><use href="#i-home"/></svg>。
 * 该精灵通过 boot 局部注入到每个布局，整个文档内 <use> 直接引用即可。
 */
?>
<svg class="apx-icon-sprite" aria-hidden="true" focusable="false" style="position:absolute;width:0;height:0;overflow:hidden">
  <defs>
    <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-7 9 7"/><path d="M5 9.5V20h14V9.5"/></symbol>
    <symbol id="i-compass" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/></symbol>
    <symbol id="i-message" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.2a3 3 0 0 1 0 5.6"/><path d="M17 20a6 6 0 0 0-3.5-5.5"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></symbol>
    <symbol id="i-group" viewBox="0 0 24 24"><circle cx="8" cy="9" r="2.6"/><circle cx="16" cy="9" r="2.6"/><path d="M3 19a5 5 0 0 1 10 0"/><path d="M11 19a5 5 0 0 1 10 0"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></symbol>
    <symbol id="i-bell-off" viewBox="0 0 24 24"><path d="M9 5.5A6 6 0 0 1 18 11c0 2 .3 3.3 1 4"/><path d="M6 8.5C5.5 9.3 5 10.6 5 13c0 4-1 5-1 5h11"/><path d="M17 17a6 6 0 0 1-9.5-1"/><path d="M3 3l18 18"/></symbol>
    <symbol id="i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 6.6 19l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 13.4H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 6.6l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 4.6V4a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8Z"/></symbol>
    <symbol id="i-sliders" viewBox="0 0 24 24"><path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h8M16 18h4"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="14" cy="18" r="2"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6Z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-shield-check" viewBox="0 0 24 24"><path d="M12 2 4 6v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6Z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20s-7-4.6-9.2-9C1.3 8 2.6 4.8 5.8 4.8c1.9 0 3.2 1.1 4.2 2.5 1-1.4 2.3-2.5 4.2-2.5 3.2 0 4.5 3.2 3 6.2C19 15.4 12 20 12 20Z"/></symbol>
    <symbol id="i-comment" viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-8 8H4l1.5-2A8 8 0 1 1 21 12Z"/></symbol>
    <symbol id="i-share" viewBox="0 0 24 24"><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="m8.2 10.8 7.6-3.6M8.2 13.2l7.6 3.6"/></symbol>
    <symbol id="i-bookmark" viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z"/></symbol>
    <symbol id="i-bookmark-plus" viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z"/><path d="M12 8v5M9.5 10.5h5"/></symbol>
    <symbol id="i-star" viewBox="0 0 24 24"><path d="m12 3 2.7 5.5 6 .9-4.3 4.2 1 6-5.4-2.8L6.6 19.6l1-6L3.3 9.4l6-.9z"/></symbol>
    <symbol id="i-flag" viewBox="0 0 24 24"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></symbol>
    <symbol id="i-more" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.5" fill="currentColor" stroke="none"/></symbol>
    <symbol id="i-more-v" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1.5" fill="currentColor" stroke="none"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5 10 17l9-10"/></symbol>
    <symbol id="i-check-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5L16 9"/></symbol>
    <symbol id="i-x-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></symbol>
    <symbol id="i-chevron-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="i-chevron-right" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-chevron-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
    <symbol id="i-arrow-right" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
    <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></symbol>
    <symbol id="i-camera" viewBox="0 0 24 24"><path d="M4 8h3l1.5-2h7L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></symbol>
    <symbol id="i-image" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.6"/><path d="m4 18 5-5 4 4 3-3 4 4"/></symbol>
    <symbol id="i-video" viewBox="0 0 24 24"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/></symbol>
    <symbol id="i-smile" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.5 2 3.5 2 3.5-2 3.5-2"/><circle cx="9" cy="10" r="1"/><circle cx="15" cy="10" r="1"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24"><path d="M4 12 20 4l-4 16-4-7z"/></symbol>
    <symbol id="i-location" viewBox="0 0 24 24"><path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></symbol>
    <symbol id="i-fire" viewBox="0 0 24 24"><path d="M12 3c1 3-2 4-2 7a3 3 0 0 0 6 0c0-1 0-1.5-.5-2.5C17 10 19 13 19 16a7 7 0 0 1-14 0c0-5 5-7 7-13Z"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-eye-off" viewBox="0 0 24 24"><path d="M3 3l18 18"/><path d="M10.6 6.2A9.7 9.7 0 0 1 12 6c6 0 10 6 10 6a16 16 0 0 1-3 3.4"/><path d="M6 7.5C3.7 9 2 12 2 12s4 6 10 6a9.5 9.5 0 0 0 4-.9"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></symbol>
    <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="8" r="4"/><path d="m11 11 8 8M16 16l2-2M19 19l2-2"/></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/></symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14a8 8 0 1 1-9.5-9.8A6.5 6.5 0 0 0 20 14Z"/></symbol>
    <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></symbol>
    <symbol id="i-volume" viewBox="0 0 24 24"><path d="M4 9v6h4l5 4V5L8 9z"/><path d="M16 9a4 4 0 0 1 0 6"/></symbol>
    <symbol id="i-wifi" viewBox="0 0 24 24"><path d="M2 8.5a16 16 0 0 1 20 0"/><path d="M5 12a11 11 0 0 1 14 0"/><path d="M8 15.5a6 6 0 0 1 8 0"/><circle cx="12" cy="19" r="1"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v12M7 11l5 5 5-5M5 21h14"/></symbol>
    <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 21V9M7 13l5-5 5 5M5 21h14"/></symbol>
    <symbol id="i-filter" viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></symbol>
    <symbol id="i-sparkles" viewBox="0 0 24 24"><path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5z"/><path d="M18 14l.8 2.2L21 17l-2.2.8L18 20l-.8-2.2L15 17l2.2-.8z"/></symbol>
    <symbol id="i-sticker" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="10" r="1"/><circle cx="15" cy="10" r="1"/><path d="M8.5 14.5s1.5 2 3.5 2 3.5-2 3.5-2"/></symbol>
    <symbol id="i-phone" viewBox="0 0 24 24"><path d="M5 4h3l2 5-2 1a12 12 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></symbol>
    <symbol id="i-play" viewBox="0 0 24 24"><path d="M7 5l12 7-12 7z"/></symbol>
    <symbol id="i-pause" viewBox="0 0 24 24"><path d="M8 5v14M16 5v14"/></symbol>
    <symbol id="i-paperclip" viewBox="0 0 24 24"><path d="M20 11l-8 8a5 5 0 0 1-7-7l8-8a3.5 3.5 0 0 1 5 5l-8 8a2 2 0 0 1-3-3l7-7"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7L11 7"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7L13 17"/></symbol>
    <symbol id="i-at" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></symbol>
    <symbol id="i-hash" viewBox="0 0 24 24"><path d="M5 9h14M5 15h14M10 3 8 21M16 3l-2 18"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 12h10M17 8l4 4-4 4"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 3 2 20h20L12 3Z"/><path d="M12 9v5M12 17h.01"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-trending-up" viewBox="0 0 24 24"><path d="M3 17l6-6 4 4 8-8M21 7h-5M21 7v5"/></symbol>
    <symbol id="i-layers" viewBox="0 0 24 24"><path d="M12 3 2 8l10 5 10-5z"/><path d="M2 13l10 5 10-5M2 17l10 5 10-5"/></symbol>
    <symbol id="i-zap" viewBox="0 0 24 24"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></symbol>
    <symbol id="i-user-plus" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M18 7v6M15 10h6"/></symbol>
    <symbol id="i-user-minus" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M15 10h6"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M4 12a8 8 0 0 1 14-5l2 2M20 12a8 8 0 0 1-14 5l-2-2"/><path d="M20 4v5h-5M4 20v-5h5"/></symbol>
    <symbol id="i-cpu" viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/></symbol>
    <symbol id="i-database" viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/></symbol>
    <symbol id="i-award" viewBox="0 0 24 24"><circle cx="12" cy="9" r="5"/><path d="m8.5 13-2 7 5.5-3 5.5 3-2-7"/></symbol>
    <symbol id="i-gift" viewBox="0 0 24 24"><rect x="3" y="9" width="18" height="11" rx="1.5"/><path d="M3 13h18M12 9v11M12 9S9 4 6.5 5.5 9 9 12 9zM12 9s3-5 5.5-3.5S15 9 12 9z"/></symbol>
    <symbol id="i-music" viewBox="0 0 24 24"><path d="M9 18a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M9 12V5l10-2v9"/><circle cx="17" cy="14" r="3"/></symbol>
    <symbol id="i-cloud" viewBox="0 0 24 24"><path d="M7 18a4 4 0 0 1 0-8 5 5 0 0 1 9.5-1.5A4 4 0 0 1 17 18z"/></symbol>
    <symbol id="i-mic" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></symbol>
    <symbol id="i-headphones" viewBox="0 0 24 24"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="7" rx="1.5"/><rect x="17" y="13" width="4" height="7" rx="1.5"/></symbol>
    <symbol id="i-tag" viewBox="0 0 24 24"><path d="M3 12V4h8l9 9-8 8z"/><circle cx="8" cy="8" r="1.4"/></symbol>
    <symbol id="i-inbox" viewBox="0 0 24 24"><path d="M3 13l3-8h12l3 8v6H3z"/><path d="M3 13h5l1 2h6l1-2h5"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></symbol>
    <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 4.5 1.5c0 1.5-2 2-2 3.5M12 17h.01"/></symbol>
    <symbol id="i-ban" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></symbol>
    <symbol id="i-palette" viewBox="0 0 24 24"><path d="M12 3a9 9 0 0 0 0 18c1.7 0 2-1.3 1-2.2-1-1 0-2.3 1.4-2.3H17a4 4 0 0 0 4-4A9 9 0 0 0 12 3Z"/><circle cx="7.5" cy="11" r="1"/><circle cx="9.5" cy="7" r="1"/><circle cx="14.5" cy="7" r="1"/><circle cx="16.5" cy="11" r="1"/></symbol>
    <symbol id="i-inbox-full" viewBox="0 0 24 24"><path d="M3 13l3-8h12l3 8v6H3z"/><path d="M3 13h5l1 2h6l1-2h5M9 9h6"/></symbol>
    <symbol id="i-cog" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8 2 2 0 1 1-2.8 2.8 1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 6.6 19a2 2 0 1 1-2.8-2.8A1.6 1.6 0 0 0 3 13.4H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 6.6a2 2 0 1 1 2.8-2.8A1.6 1.6 0 0 0 10 4.6V4a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1 2 2 0 1 1 2.8 2.8 1.6 1.6 0 0 0-.3 1.8Z"/></symbol>
  </defs>
</svg>
