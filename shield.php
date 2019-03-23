<?php
$pct = number_format(99.845, 1);

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="146" height="20"><linearGradient id="b" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient><clipPath id="a"><rect width="146" height="20" rx="3" fill="#fff"/></clipPath><g clip-path="url(#a)"><path fill="#555" d="M0 0h99v20H0z"/><path fill="#6370b5" d="M99 0h47v20H99z"/><path fill="url(#b)" d="M0 0h146v20H0z"/></g><g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="110"> <text x="505" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="890">psalm-coverage</text><text x="505" y="140" transform="scale(.1)" textLength="890">psalm-coverage</text><text x="1215" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="370">{$pct}%</text><text x="1215" y="140" transform="scale(.1)" textLength="370">99.8%</text></g> </svg>
SVG;
