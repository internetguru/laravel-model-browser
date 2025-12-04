<?php

namespace Internetguru\ModelBrowser\Traits;

trait HighlightMatchesTrait
{
    protected function exactMatchFilter(&$filter): bool
    {
        // Check if the filter is enclosed in double quotes for exact matching
        if (strlen($filter) >= 2 && $filter[0] === '"' && $filter[strlen($filter) - 1] === '"') {
            $filter = substr($filter, 1, -1);
            return true;
        }
        return false;
    }

    protected function highlightMatches($data, string $filter, array $filterAttributes): mixed
    {
        if (! $filter) {
            return $data;
        }

        $exactMatch = $this->exactMatchFilter($filter);
        $normalizedFilter = mb_strtolower($this->removeAccents($filter));

        $data->transform(function ($item) use ($normalizedFilter, $filterAttributes, $exactMatch) {
            // Highlight matches in each filter attribute
            foreach ($filterAttributes as $attribute) {
                $originalValue = $item->{$attribute . 'Formatted'} ?? $item->{$attribute};

                if (! $exactMatch) {
                    $normalizedFilter = trim($normalizedFilter);
                }

                if ($exactMatch && $normalizedFilter == '') {
                    // Exact match with empty string
                    $item->{$attribute . 'Highlighted'} = $originalValue == '' ? '<mark></mark>' : $originalValue;
                    continue;
                }
                if (! $originalValue) {
                    continue;
                }

                // Apply highlighting while preserving HTML structure
                $item->{$attribute . 'Highlighted'} = $this->highlightText($originalValue, $normalizedFilter, $exactMatch);
            }

            return $item;
        });

        return $data;
    }

    protected function highlightText($htmlContent, $normalizedFilter, bool $exactMatch = false)
    {
        // For empty content, return as is
        if (!$htmlContent) {
            return $htmlContent;
        }

        // For all types of matching, use DOM parsing to preserve HTML structure
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        https: //github.com/symfony/symfony/issues/44281#issuecomment-1647665965
        $encoded = mb_encode_numericentity(
            htmlspecialchars_decode(
                htmlentities($htmlContent, ENT_NOQUOTES, 'UTF-8', false),
                ENT_NOQUOTES
            ),
            [0x80, 0x10FFFF, 0, ~0],
            'UTF-8'
        );
        $dom->loadHTML($encoded);
        libxml_clear_errors();

        // Handle exact matching
        if ($exactMatch && $normalizedFilter !== '') {
            // Get concatenated text content without HTML tags
            $textContent = $this->getDomTextContent($dom);

            // Check if the extracted text matches exactly
            $normalizedContent = mb_strtolower($this->removeAccents($textContent));
            if ($normalizedContent == $normalizedFilter) {
                return '<mark>' . $htmlContent . '</mark>';
            }
            return $htmlContent;
        }

        // For partial matching, traverse and highlight text nodes
        $this->highlightDomNode($dom, $dom->documentElement, $normalizedFilter);

        // Extract and return the modified HTML
        return $this->getInnerHtml($dom);
    }

    protected function getDomTextContent($dom): string
    {
        $textContent = '';
        $xpath = new \DOMXPath($dom);
        $textNodes = $xpath->query('//text()');
        foreach ($textNodes as $node) {
            $textContent .= $node->nodeValue;
        }
        return $textContent;
    }

    protected function getInnerHtml($dom): string
    {
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
