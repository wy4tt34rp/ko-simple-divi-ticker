=== KO Simple Divi Ticker ===
Stable tag: 1.7.0

= 1.7.0 =
* Fix: overlays/popups that change viewport width (e.g., Divi Overlays) no longer cause the ticker to speed up.
* Prevents double-wrapping by storing/restoring original ticker HTML before rebuilds.
* Uses ResizeObserver per ticker so scrollbar/overlay width changes reinitialize safely.
