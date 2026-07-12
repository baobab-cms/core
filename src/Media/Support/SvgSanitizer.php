<?php

declare(strict_types=1);

namespace Baobab\Media\Support;

use Baobab\Media\Exceptions\InvalidMediaUploadException;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Désinfection SVG minimale (spec 06 §3.2, règle non négociable) : retrait
 * des <script>, des attributs on* (gestionnaires d'événements) et des URIs
 * javascript: dans href/xlink:href — le SVG est le vecteur XSS classique
 * d'une bibliothèque de médias. Pas de dépendance ajoutée (décision validée) ;
 * une couverture plus large (CSS externe, entités XML) resterait à faire via
 * une vraie librairie si un besoin réel apparaît.
 */
final class SvgSanitizer
{
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    public function sanitize(string $svg): string
    {
        $document = new DOMDocument;

        $previousUseErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if (! $loaded) {
            throw InvalidMediaUploadException::malformedSvg();
        }

        $this->removeScriptElements($document);
        $this->removeEventHandlerAttributes($document);
        $this->removeJavascriptUris($document);

        $sanitized = $document->saveXML();

        if ($sanitized === false) {
            throw InvalidMediaUploadException::malformedSvg();
        }

        return $sanitized;
    }

    private function removeScriptElements(DOMDocument $document): void
    {
        foreach (iterator_to_array($document->getElementsByTagName('script')) as $script) {
            $script->parentNode?->removeChild($script);
        }
    }

    private function removeEventHandlerAttributes(DOMDocument $document): void
    {
        foreach ($this->queryElements($document, '//*') as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                if (str_starts_with(strtolower($attribute->name), 'on')) {
                    $element->removeAttribute($attribute->name);
                }
            }
        }
    }

    private function removeJavascriptUris(DOMDocument $document): void
    {
        foreach (['href' => '//*[@href]', 'xlink:href' => '//*[@xlink:href]'] as $attribute => $query) {
            foreach ($this->queryElements($document, $query) as $element) {
                $value = trim($attribute === 'xlink:href'
                    ? $element->getAttributeNS(self::XLINK_NAMESPACE, 'href')
                    : $element->getAttribute($attribute));

                if (str_starts_with(strtolower($value), 'javascript:')) {
                    if ($attribute === 'xlink:href') {
                        $element->removeAttributeNS(self::XLINK_NAMESPACE, 'href');
                    } else {
                        $element->removeAttribute($attribute);
                    }
                }
            }
        }
    }

    /**
     * @return list<DOMElement>
     */
    private function queryElements(DOMDocument $document, string $query): array
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xlink', self::XLINK_NAMESPACE);

        $nodes = $xpath->query($query);

        if ($nodes === false) {
            return [];
        }

        return array_values(array_filter(iterator_to_array($nodes), fn ($node): bool => $node instanceof DOMElement));
    }
}
