<?php

namespace ShopCode\Services;

/**
 * Streamovací parser Shoptet XML feedu.
 * Zpracovává soubor po produktech — minimální RAM usage.
 *
 * Shoptet XML struktura (typická):
 * <SHOP>
 *   <SHOPITEM>
 *     <ITEM_ID>...</ITEM_ID>
 *     <PRODUCTNAME>...</PRODUCTNAME>
 *     <DESCRIPTION>...</DESCRIPTION>
 *     <URL>...</URL>
 *     <PRICE_VAT>...</PRICE_VAT>
 *     <CURRENCY>...</CURRENCY>
 *     <CATEGORYTEXT>...</CATEGORYTEXT>
 *     <MANUFACTURER>...</MANUFACTURER>
 *     <AVAILABILITY_OUT_OF_STOCK>...</AVAILABILITY_OUT_OF_STOCK>
 *     <IMGURL>...</IMGURL>
 *     <IMGURL_ALTERNATIVE>...</IMGURL_ALTERNATIVE>
 *     <PARAM>
 *       <PARAM_NAME>...</PARAM_NAME>
 *       <VAL>...</VAL>
 *     </PARAM>
 *     <VARIANT>
 *       <VARIANT_ID>...</VARIANT_ID>
 *       <PRODUCTNAME>...</PRODUCTNAME>
 *       <PRICE_VAT>...</PRICE_VAT>
 *       <STOCK_QUANTITY>...</STOCK_QUANTITY>
 *       <PARAM>...</PARAM>
 *     </VARIANT>
 *   </SHOPITEM>
 * </SHOP>
 */
class XmlParser
{
    // Mapování XML tagů → DB sloupce (pevné, bez konfigurace)
    private const FIELD_MAP = [
        'ITEM_ID'                    => 'shoptet_id',
        'PRODUCTNAME'                => 'name',
        'DESCRIPTION'                => 'description',
        'PRICE_VAT'                  => 'price',
        'CURRENCY'                   => 'currency',
        'CATEGORYTEXT'               => 'category',
        'MANUFACTURER'               => 'brand',
        'AVAILABILITY_OUT_OF_STOCK'  => 'availability',
    ];

    // Tagy obrázků
    private const IMAGE_TAGS = ['IMGURL', 'IMGURL_ALTERNATIVE'];

    // Název kořenového elementu produktu
    private const PRODUCT_ELEMENT = 'SHOPITEM';
    private const VARIANT_ELEMENT = 'VARIANT';
    private const PARAM_ELEMENT   = 'PARAM';

    /**
     * Streamovací iterátor — vrací produkty jeden po jednom.
     * Callback dostane jeden produkt jako array.
     *
     * @param string   $filePath   Cesta k XML souboru
     * @param callable $callback   function(array $product, array $variants): void
     * @param callable $progress   function(int $processed): void  (volitelné)
     * @return array{processed: int, errors: int, error_log: string[]}
     */
    public static function stream(
        string   $filePath,
        callable $callback,
        callable $progress = null
    ): array {
        $reader = new \XMLReader();

        if (!$reader->open($filePath, 'UTF-8', LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \RuntimeException("Nelze otevřít XML soubor: {$filePath}");
        }

        $processed = 0;
        $errors    = 0;
        $errorLog  = [];

        // Projdeme XML, hledáme <SHOPITEM>
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) continue;
            if ($reader->localName  !== self::PRODUCT_ELEMENT) continue;

            try {
                // Načteme celý <SHOPITEM> jako SimpleXML node (malý — jen jeden produkt)
                $node = simplexml_import_dom(
                    (new \DOMDocument())->importNode($reader->expand(), true)
                );

                [$product, $variants] = self::parseProductNode($node);

                if (empty($product['shoptet_id'])) {
                    $errors++;
                    $errorLog[] = "Produkt bez ITEM_ID přeskočen";
                    continue;
                }

                $callback($product, $variants);
                $processed++;

                if ($progress && $processed % 100 === 0) {
                    $progress($processed);
                }

            } catch (\Throwable $e) {
                $errors++;
                $errorLog[] = "Chyba parsování produktu: " . $e->getMessage();
                if (count($errorLog) > 100) {
                    array_shift($errorLog); // Nepřetečeme paměť v error logu
                }
            }
        }

        $reader->close();

        return [
            'processed' => $processed,
            'errors'    => $errors,
            'error_log' => $errorLog,
        ];
    }

    /**
     * Parsuje SimpleXML node jednoho produktu
     */
    private static function parseProductNode(\SimpleXMLElement $node): array
    {
        $product  = [];
        $images   = [];
        $params   = [];
        $variants = [];

        // Skalární pole
        foreach (self::FIELD_MAP as $xmlTag => $dbField) {
            $val = isset($node->$xmlTag) ? trim((string)$node->$xmlTag) : null;
            $product[$dbField] = $val !== '' ? $val : null;
        }

        // Cena — číslo
        if (isset($product['price'])) {
            $product['price'] = self::parseDecimal($product['price']);
        }

        // Měna — výchozí CZK
        if (empty($product['currency'])) {
            $product['currency'] = 'CZK';
        }

        // Obrázky
        foreach (self::IMAGE_TAGS as $tag) {
            if (isset($node->$tag)) {
                $url = trim((string)$node->$tag);
                if ($url) $images[] = $url;
            }
        }
        // Alternativní obrázky (může být víc)
        foreach ($node->IMGURL_ALTERNATIVE ?? [] as $img) {
            $url = trim((string)$img);
            if ($url && !in_array($url, $images)) $images[] = $url;
        }
        $product['images'] = !empty($images) ? json_encode($images, JSON_UNESCAPED_UNICODE) : null;

        // Parametry
        foreach ($node->PARAM ?? [] as $param) {
            $name = trim((string)($param->PARAM_NAME ?? ''));
            $val  = trim((string)($param->VAL ?? ''));
            if ($name) $params[$name] = $val;
        }
        $product['parameters'] = !empty($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : null;

        // Raw XML data (pro budoucí použití)
        $product['xml_data'] = null;

        // Varianty
        foreach ($node->VARIANT ?? [] as $varNode) {
            $variant = self::parseVariantNode($varNode);
            if (!empty($variant['shoptet_variant_id'])) {
                $variants[] = $variant;
            }
        }

        return [$product, $variants];
    }

    /**
     * Parsuje variantu produktu
     */
    private static function parseVariantNode(\SimpleXMLElement $node): array
    {
        $params = [];
        foreach ($node->PARAM ?? [] as $p) {
            $name = trim((string)($p->PARAM_NAME ?? ''));
            $val  = trim((string)($p->VAL ?? ''));
            if ($name) $params[$name] = $val;
        }

        $stock = isset($node->STOCK_QUANTITY) ? (int)(string)$node->STOCK_QUANTITY : 0;

        return [
            'shoptet_variant_id' => trim((string)($node->VARIANT_ID ?? '')),
            'name'               => trim((string)($node->PRODUCTNAME ?? '')) ?: null,
            'price'              => self::parseDecimal((string)($node->PRICE_VAT ?? '')),
            'stock'              => $stock,
            'parameters'         => !empty($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    /**
     * Bezpečný převod řetězce na decimal
     */
    private static function parseDecimal(string $val): ?float
    {
        $val = str_replace([' ', ','], ['', '.'], trim($val));
        return is_numeric($val) ? (float)$val : null;
    }
}
