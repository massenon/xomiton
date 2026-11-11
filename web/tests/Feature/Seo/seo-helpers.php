<?php

declare(strict_types=1);

/*
 * Aides communes aux audits SEO (SeoAuditTest, SeoDynamicPagesAuditTest) : charge une page et la parse en DOM.
 */

/** @return array{0: DOMDocument, 1: DOMXPath, 2: string} */
function seoDom(string $url): array
{
    $html = (string) test()->get($url)->assertOk()->getContent();
    $doc = new DOMDocument;
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
    libxml_clear_errors();

    return [$doc, new DOMXPath($doc), $html];
}

function seoMeta(DOMXPath $x, string $attr, string $name): ?string
{
    $n = $x->query("//meta[@$attr='$name']")->item(0);

    return $n instanceof DOMElement ? $n->getAttribute('content') : null;
}
