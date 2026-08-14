<?php

declare(strict_types=1);

namespace Baobab\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-baobab::form method="PATCH">` — formulaire admin : jeton CSRF, method
 * spoofing et rappel des erreurs de validation, sans que l'appelant y pense.
 *
 * La normalisation de la méthode vivait dans un bloc `@php` de la vue (suivi
 * n° 138) : décider qu'un `PATCH` se poste en `POST` accompagné d'un `@method`
 * est une règle HTTP, pas de l'affichage.
 *
 * `$attributes` n'a plus à exclure `method` : la propriété étant portée par le
 * constructeur, Laravel la retire lui-même du sac d'attributs.
 */
final class Form extends Component
{
    /** @var list<string> */
    private const SPOOFED = ['PUT', 'PATCH', 'DELETE'];

    public string $httpMethod;

    public bool $isSpoofed;

    public string $formMethod;

    public function __construct(string $method = 'POST')
    {
        $this->httpMethod = strtoupper($method);
        $this->isSpoofed = in_array($this->httpMethod, self::SPOOFED, true);
        $this->formMethod = $this->isSpoofed ? 'POST' : $this->httpMethod;
    }

    public function render(): View
    {
        return view('baobab::components.form');
    }
}
