/*
 * Écran de progression du wizard d'installation — spec 15 §6.1, suivi n° 224.
 *
 * Il ne fait qu'une chose : demander au serveur d'avancer d'une étape, puis
 * recommencer tant qu'il en reste. C'est le §6.1 qui l'impose — `RunMigrations`
 * est l'étape longue, et une requête unique portant toute la séquence se ferait
 * tuer par les limites d'un hébergement mutualisé.
 *
 * **L'avancement affiché ne peut donc pas mentir** : il n'existe qu'à mesure
 * que les requêtes reviennent. C'est exactement ce que `direction-visuelle.md`
 * demande — « progression réelle par étape », « ne pas la remplacer par une
 * barre abstraite » — et la contrainte du mutualisé produit ici l'honnêteté que
 * l'esthétique réclamait.
 *
 * Sans JavaScript, la page reste utilisable : le formulaire de repli exécute
 * une étape par clic. C'est laid, et c'est mieux qu'une page qui ne fait rien.
 */
(function () {
    'use strict';

    var run = document.getElementById('run');

    if (!run) {
        return;
    }

    var endpoint = run.dataset.endpoint;
    var token = run.dataset.token;
    var error = document.getElementById('run-error');
    var actions = document.getElementById('run-actions');

    // Le repli n'a de sens que sans JavaScript : puisqu'on en exécute, on le
    // retire plutôt que de laisser deux façons concurrentes d'avancer.
    if (actions) {
        actions.hidden = true;
    }

    function stepNode(key) {
        return run.querySelector('[data-step="' + key + '"]');
    }

    function mark(node, state, label) {
        if (!node) {
            return;
        }

        node.classList.remove('is-running', 'is-done', 'is-failed');
        node.classList.add(state);

        var slot = node.querySelector('[data-role="state"]');

        if (slot) {
            slot.textContent = label;
        }
    }

    /*
     * Les détails d'une étape — aujourd'hui les migrations réellement passées.
     * `textContent` et non `innerHTML` : ces chaînes viennent du serveur, mais
     * elles décrivent des fichiers dont le nom n'est pas sous notre contrôle,
     * et une page d'installation est le dernier endroit où prendre ce risque.
     */
    function detail(node, lines) {
        if (!node || !lines || lines.length === 0) {
            return;
        }

        var list = node.querySelector('[data-role="details"]');

        if (!list) {
            return;
        }

        lines.forEach(function (line) {
            var item = document.createElement('li');
            item.textContent = line;
            list.appendChild(item);
        });
    }

    function finish(adminUrl) {
        var done = document.createElement('p');
        done.className = 'lede is-finished';
        done.textContent = 'Votre site est installé. L’installateur vient de se refermer : cette adresse ne répondra plus.';

        var link = document.createElement('a');
        link.className = 'button button--link';
        link.href = adminUrl || '/admin';
        link.textContent = 'Aller à l’administration';

        var wrapper = document.createElement('p');
        wrapper.className = 'actions';
        wrapper.appendChild(link);

        run.parentNode.insertBefore(done, run.nextSibling);
        done.parentNode.insertBefore(wrapper, done.nextSibling);
    }

    function fail(message) {
        if (!error) {
            return;
        }

        error.textContent = message;
        error.hidden = false;
    }

    /*
     * Décrit une réponse que l'on n'a pas su lire.
     *
     * Le corps est tronqué : une page d'erreur PHP fait des kilo-octets, et
     * l'utile — le message, la classe d'exception — tient dans les premières
     * lignes. Les balises sont retirées pour que le texte reste lisible dans
     * une bannière ; on ne cherche pas à rendre du HTML, on cherche à le citer.
     */
    function describe(response, body) {
        var texte = String(body || '')
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 300);

        var parts = ['Réponse inattendue du serveur (HTTP ' + response.status + ')'];

        if (response.redirected) {
            parts.push('redirigé vers ' + response.url);
        }

        if (texte !== '') {
            parts.push(texte);
        }

        return parts.join(' — ');
    }

    function next() {
        var pending = run.querySelector('.run__step:not(.is-done):not(.is-failed)');

        if (pending) {
            mark(pending, 'is-running', 'en cours');
        }

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                /*
                 * **Ce que le serveur a réellement répondu, et pas une
                 * paraphrase.** Ce bloc rendait « Réponse inattendue du
                 * serveur » quoi qu'il arrive. Le 2 septembre 2026, cette
                 * phrase a caché pendant deux recettes une redirection 302
                 * vers la porte d'installation : la session avait été perdue,
                 * et l'écran ne pouvait pas le dire (suivi n° 226).
                 *
                 * `redirected` et `url` sont les deux seules choses qui
                 * nommaient la cause. On les rend, avec le code HTTP et le
                 * début du corps — un installateur qui échoue doit laisser une
                 * trace lisible par quelqu'un qui n'a ni shell ni journaux.
                 */
                return response.text().then(function (body) {
                    try {
                        return JSON.parse(body);
                    } catch (e) {
                        throw new Error(describe(response, body));
                    }
                });
            })
            .then(function (payload) {
                if (payload.failed) {
                    mark(pending, 'is-failed', 'échec');
                    fail(payload.message || 'L’installation s’est interrompue.');

                    return;
                }

                var node = stepNode(payload.step) || pending;

                if (payload.step) {
                    mark(node, 'is-done', 'fait');
                    detail(node, payload.details);
                }

                if (payload.done) {
                    /*
                     * Surtout pas de rechargement, ni de requête de plus : la
                     * finalisation vient d'écrire le lock, donc `/install/*`
                     * répond 404 dès l'instant d'après (§6.3). La réponse qui
                     * finalise est la dernière que nous verrons — on rend la
                     * fin avec elle.
                     */
                    finish(payload.adminUrl);

                    return;
                }

                next();
            })
            .catch(function (reason) {
                mark(pending, 'is-failed', 'échec');
                fail(reason.message || 'La connexion au serveur a été perdue.');
            });
    }

    next();
})();
