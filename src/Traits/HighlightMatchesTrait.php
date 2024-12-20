<?php

namespace Internetguru\ModelBrowser\Traits;

trait HighlightMatchesTrait
{
    protected function highlightMatches($data, string $filter, array $filterAttributes, callable $removeAccents): mixed
    {
        if (! $filter) {
            return $data;
        }

        $normalizedFilter = mb_strtolower($removeAccents($filter));
        $filterLength = mb_strlen($normalizedFilter);

        $data->getCollection()->transform(function ($item) use ($normalizedFilter, $filterAttributes) {
            // Highlight matches in each filter attribute
            foreach ($filterAttributes as $attribute) {
                $originalValue = $item->{$attribute . 'Formatted'} ?? $item->{$attribute};

                if (! $originalValue) {
                    continue;
                }

                // Apply highlighting while preserving HTML structure
                $item->{$attribute . 'Highlighted'} = $this->highlightText($originalValue, $normalizedFilter);
            }

            return $item;
        });

        return $data;
    }

    protected function highlightText($htmlContent, $normalizedFilter)
    {
        $dom = new \DOMDocument;

        // Suppress errors due to invalid HTML snippets
        libxml_use_internal_errors(true);
        // Load the HTML content
        $dom->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        // Traverse text nodes and apply highlighting
        $this->highlightDomNode($dom, $dom->documentElement, $normalizedFilter);

        // Save and return the modified HTML
        $body = $dom->getElementsByTagName('body')->item(0);
        $innerHTML = '';
        foreach ($body->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }

        return $innerHTML;
    }

    protected function highlightDomNode($dom, $node, $normalizedFilter)
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $originalText = $node->nodeValue;
            $normalizedText = mb_strtolower($this->removeAccents($originalText));

            if (mb_strpos($normalizedText, $normalizedFilter) !== false) {
                // Split the text and insert <mark> tags
                $newHtml = $this->addMarksAroundMatches($originalText, $normalizedText, $normalizedFilter);
                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($newHtml);
                $node->parentNode->replaceChild($fragment, $node);
            }
        } elseif ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->highlightDomNode($dom, $child, $normalizedFilter);
            }
        }
    }

    protected function addMarksAroundMatches($text, $normalizedText, $normalizedFilter)
    {
        $escapedText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $normalizedEscapedText = mb_strtolower($this->removeAccents($escapedText));

        $highlightedText = '';
        $offset = 0;

        while (($pos = mb_strpos($normalizedEscapedText, $normalizedFilter, $offset)) !== false) {
            $highlightedText .= mb_substr($escapedText, $offset, $pos - $offset);
            $highlightedText .= '<mark>' . mb_substr($escapedText, $pos, mb_strlen($normalizedFilter)) . '</mark>';
            $offset = $pos + mb_strlen($normalizedFilter);
        }

        $highlightedText .= mb_substr($escapedText, $offset);

        return $highlightedText;
    }

    protected function removeAccents($string)
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
    }
}
