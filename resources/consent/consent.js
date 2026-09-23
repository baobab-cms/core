/*
 * Baobab — consentement aux cookies (spec 16 §3.2, M9 chantier 0.b Pass F1).
 *
 * Script vendored par le Core, sans dépendance ni build : publié sous
 * /baobab/consent/ et chargé par <x-baobab::consent-banner />, uniquement
 * quand un module actif déclare une catégorie autre que `necessary`
 * (décision 21). Il ne parle jamais au serveur : le choix vit dans le cookie
 * first-party `baobab_consent`, que le serveur ne lit pas (décision 20).
 *
 * API publique — window.Baobab.consent :
 *   has(category)                    → true si la catégorie est accordée
 *                                      (`necessary` l'est toujours)
 *   on('granted'|'revoked', category, callback)
 *                                    → `granted` rappelle aussitôt si la
 *                                      catégorie est déjà accordée
 *   open()                           → rouvre la bannière (retrait du
 *                                      consentement, RGPD art. 7.3)
 *
 * Focus : au premier affichage, la bannière reste non modale — la page reste
 * atteignable au clavier, pas de mur de consentement. Rouverte par open(),
 * elle devient modale (aria-modal="true") : Tab et Shift+Tab bouclent dans
 * la bannière, Échap la referme et le focus revient à l'élément d'origine.
 *
 * File d'appels : un script chargé avant celui-ci peut écrire
 *   (window.Baobab = window.Baobab || {}).consent = window.Baobab.consent || [];
 *   window.Baobab.consent.push(function (consent) { ... });
 * chaque fonction est appelée avec l'API dès qu'elle existe.
 *
 * Scripts conditionnés : <script type="text/plain" data-baobab-consent="analytics" src="…">
 * est activé (recréé en script exécutable) dès que sa catégorie est accordée.
 *
 * Contrat du balisage (bannière Core ou surcharge `partials/cookie-banner`
 * d'un thème — présentation pure, le comportement est ici) :
 *   [data-baobab-consent-banner]        racine, rendue avec `hidden`
 *   [data-baobab-consent-action=…]      accept-all | reject-all | customize | save
 *   [data-baobab-consent-panel]         détail par catégorie, rendu avec `hidden`
 *   input[data-baobab-consent-category] une case par catégorie proposée
 *   [data-baobab-consent-open]          lien de réouverture, rendu avec `hidden`
 */
(function () {
    'use strict';

    var COOKIE = 'baobab_consent';
    var config = document.querySelector('script[data-baobab-consent-config]');

    if (!config) {
        return;
    }

    var categories = (config.getAttribute('data-categories') || '').split(',').filter(Boolean);
    var fingerprint = config.getAttribute('data-fingerprint') || '';
    var lifetimeDays = parseInt(config.getAttribute('data-lifetime') || '180', 10);
    var listeners = { granted: {}, revoked: {} };
    var state = read();
    var banner = null;
    var returnFocus = null;
    var modal = false;

    function read() {
        var match = document.cookie.match(/(?:^|;\s*)baobab_consent=([^;]*)/);

        if (!match) {
            return null;
        }

        try {
            var value = JSON.parse(decodeURIComponent(match[1]));
            var expired = typeof value.t !== 'number' || value.t + lifetimeDays * 864e5 < Date.now();

            if (value.f !== fingerprint || expired || !Array.isArray(value.c)) {
                return null;
            }

            return value;
        } catch (error) {
            return null;
        }
    }

    function write(granted) {
        var value = { v: 1, c: granted, f: fingerprint, t: Date.now() };
        var cookie = COOKIE + '=' + encodeURIComponent(JSON.stringify(value))
            + '; Max-Age=' + (lifetimeDays * 86400)
            + '; Path=/; SameSite=Lax';

        if (location.protocol === 'https:') {
            cookie += '; Secure';
        }

        document.cookie = cookie;

        return value;
    }

    function has(category) {
        if (category === 'necessary') {
            return true;
        }

        return state !== null && state.c.indexOf(category) !== -1;
    }

    function emit(event, category) {
        (listeners[event][category] || []).forEach(function (callback) {
            try {
                callback(category);
            } catch (error) {
                window.console && console.error(error);
            }
        });
    }

    function on(event, category, callback) {
        if (!listeners[event] || typeof callback !== 'function') {
            return;
        }

        (listeners[event][category] = listeners[event][category] || []).push(callback);

        if (event === 'granted' && has(category)) {
            callback(category);
        }
    }

    function activateScripts() {
        var pending = document.querySelectorAll('script[type="text/plain"][data-baobab-consent]');

        Array.prototype.forEach.call(pending, function (placeholder) {
            if (!has(placeholder.getAttribute('data-baobab-consent'))) {
                return;
            }

            var script = document.createElement('script');

            Array.prototype.forEach.call(placeholder.attributes, function (attribute) {
                if (attribute.name !== 'type' && attribute.name !== 'data-baobab-consent') {
                    script.setAttribute(attribute.name, attribute.value);
                }
            });

            if (!placeholder.src) {
                script.text = placeholder.text;
            }

            placeholder.parentNode.replaceChild(script, placeholder);
        });
    }

    function decide(granted) {
        var before = categories.filter(has);

        state = write(granted.filter(function (category) {
            return categories.indexOf(category) !== -1;
        }));

        categories.forEach(function (category) {
            var was = before.indexOf(category) !== -1;

            if (!was && has(category)) {
                emit('granted', category);
            } else if (was && !has(category)) {
                emit('revoked', category);
            }
        });

        activateScripts();
        hide();
    }

    function checkboxes() {
        return banner ? banner.querySelectorAll('input[data-baobab-consent-category]') : [];
    }

    function panel() {
        return banner ? banner.querySelector('[data-baobab-consent-panel]') : null;
    }

    function show(moveFocus) {
        if (!banner) {
            return;
        }

        Array.prototype.forEach.call(checkboxes(), function (box) {
            box.checked = has(box.getAttribute('data-baobab-consent-category'));
        });

        banner.hidden = false;

        if (moveFocus) {
            returnFocus = document.activeElement;
            setModal(true);
            banner.setAttribute('tabindex', '-1');
            banner.focus();
        }
    }

    function setModal(value) {
        modal = value;
        banner.setAttribute('aria-modal', value ? 'true' : 'false');
    }

    function focusable() {
        var candidates = banner.querySelectorAll('button, input, select, textarea, a[href], [tabindex]:not([tabindex="-1"])');

        return Array.prototype.filter.call(candidates, function (element) {
            return !element.disabled && element.getClientRects().length > 0;
        });
    }

    function trap(event) {
        if (!modal) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();

            // Sans choix enregistré, la bannière doit rester visible : Échap
            // ne fait que la rendre à son état non modal.
            if (state === null) {
                setModal(false);
            } else {
                hide();
            }

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        var elements = focusable();

        if (elements.length === 0) {
            return;
        }

        var first = elements[0];
        var last = elements[elements.length - 1];
        var active = document.activeElement;

        if (event.shiftKey && (active === first || active === banner)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function hide() {
        if (!banner) {
            return;
        }

        banner.hidden = true;
        setModal(false);

        if (panel()) {
            panel().hidden = true;
        }

        Array.prototype.forEach.call(banner.querySelectorAll('[data-baobab-consent-action="customize"]'), function (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        });

        if (returnFocus && typeof returnFocus.focus === 'function') {
            returnFocus.focus();
        }

        returnFocus = null;
    }

    function open() {
        show(true);
    }

    function handle(action, trigger) {
        if (action === 'accept-all') {
            decide(categories.slice());
        } else if (action === 'reject-all') {
            decide([]);
        } else if (action === 'customize' && panel()) {
            panel().hidden = !panel().hidden;
            trigger.setAttribute('aria-expanded', panel().hidden ? 'false' : 'true');
        } else if (action === 'save') {
            var granted = [];

            Array.prototype.forEach.call(checkboxes(), function (box) {
                if (box.checked) {
                    granted.push(box.getAttribute('data-baobab-consent-category'));
                }
            });

            decide(granted);
        }
    }

    function init() {
        banner = document.querySelector('[data-baobab-consent-banner]');

        if (banner) {
            banner.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-baobab-consent-action]');

                if (trigger) {
                    event.preventDefault();
                    handle(trigger.getAttribute('data-baobab-consent-action'), trigger);
                }
            });
            banner.addEventListener('keydown', trap);
        }

        Array.prototype.forEach.call(document.querySelectorAll('[data-baobab-consent-open]'), function (link) {
            link.hidden = false;
            link.addEventListener('click', function (event) {
                event.preventDefault();
                open();
            });
        });

        activateScripts();

        if (state === null) {
            show(false);
        }
    }

    // `push` garde la file utilisable après le chargement : un script
    // tardif qui écrit `Baobab.consent.push(fn)` est servi immédiatement.
    var api = {
        has: has,
        on: on,
        open: open,
        push: function (callback) {
            if (typeof callback === 'function') {
                callback(api);
            }
        },
    };
    var root = window.Baobab = window.Baobab || {};
    var queue = Array.isArray(root.consent) ? root.consent : [];

    root.consent = api;
    queue.forEach(function (callback) {
        if (typeof callback === 'function') {
            callback(api);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
