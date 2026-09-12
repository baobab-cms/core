<?php

declare(strict_types=1);

namespace Baobab\Media\Support;

use Baobab\Media\Exceptions\InvalidMediaUploadException;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Désinfection SVG minimale (spec 06 §3.2, règle non négociable) : retrait
 * des éléments dangereux (`script`, mais aussi `foreignObject`, `use` et la
 * famille SMIL `animate*`/`set` qui permettent de recréer un gestionnaire
 * d'événement ou de dérober du contenu externe), des attributs on*
 * (gestionnaires d'événements) et des URIs `javascript:`/`data:`/`vbscript:`
 * ou externes `http(s):` dans href/xlink:href — le SVG est le vecteur XSS
 * classique d'une bibliothèque de médias. Pas de dépendance ajoutée
 * (décision validée) ; une couverture plus large (CSS externe, entités XML)
 * resterait à faire via une vraie librairie si un besoin réel apparaît.
 *
 * Le retrait des éléments est insensible à la casse (audit sécurité du
 * 7 septembre 2026, constat n° 2) : `getElementsByTagName('script')` seul
 * laissait passer `<SCRIPT>`.
 */
final class SvgSanitizer
{
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    /** @var list<string> */
    private const DANGEROUS_ELEMENTS = [
        'script', 'foreignobject', 'use', 'animate', 'animatetransform', 'animatemotion', 'set',
    ];

    /** @var list<string> */
    private const BLOCKED_URI_SCHEMES = ['javascript:', 'data:', 'vbscript:', 'http:', 'https:'];

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

        $this->removeDangerousElements($document);
        $this->removeEventHandlerAttributes($document);
        $this->removeDangerousUriAttributes($document);

        $sanitized = $document->saveXML();

        if ($sanitized === false) {
            throw InvalidMediaUploadException::malformedSvg();
        }

        return $sanitized;
    }

    private function removeDangerousElements(DOMDocument $document): void
    {
        foreach ($this->queryElements($document, '//*') as $element) {
            if (in_array(strtolower($element->localName ?? $element->nodeName), self::DANGEROUS_ELEMENTS, true)) {
                $element->parentNode?->removeChild($element);
            }
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

    private function removeDangerousUriAttributes(DOMDocument $document): void
    {
        foreach (['href' => '//*[@href]', 'xlink:href' => '//*[@xlink:href]'] as $attribute => $query) {
            foreach ($this->queryElements($document, $query) as $element) {
                $value = $attribute === 'xlink:href'
                    ? $element->getAttributeNS(self::XLINK_NAMESPACE, 'href')
                    : $element->getAttribute($attribute);

                if ($this->hasBlockedScheme($value)) {
                    if ($attribute === 'xlink:href') {
                        $element->removeAttributeNS(self::XLINK_NAMESPACE, 'href');
                    } else {
                        $element->removeAttribute($attribute);
                    }
                }
            }
        }
    }

    private function hasBlockedScheme(string $value): bool
    {
        $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $value));

        foreach (self::BLOCKED_URI_SCHEMES as $scheme) {
            if (str_starts_with($normalized, $scheme)) {
                return true;
            }
        }

        return false;
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
