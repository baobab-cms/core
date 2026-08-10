{{--
    Liste des noms d'icônes disponibles, partagée par tous les champs d'icône
    d'un même écran : une `<datalist>` se référence par `id`, y compris depuis
    des champs répétés dans une boucle Alpine (étape 6). Filtrage au clavier
    par le navigateur, aucun JavaScript.

    Ce n'est pas le sélecteur visuel du §11 de la direction visuelle (grille de
    prévisualisation) : celui-là est un composant admin réutilisable, consigné
    au suivi.
--}}
<datalist id="baobab-icon-catalogue">
    @foreach ($iconNames as $iconName)
        <option value="{{ $iconName }}"></option>
    @endforeach
</datalist>
