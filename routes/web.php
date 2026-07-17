<?php

// La page d'accueil (`/`) vit désormais dans PublicRouteRegistrar (spec 03
// §4, amendement du 17 juillet 2026) — elle a besoin du même middleware
// ResolveActiveTheme que le reste du rendu public, absent de ce groupe.
