<?php

namespace ShopCode\Services;

/**
 * Streamovací parser Shoptet Marketing XML feedu.
 *
 * Skutečná struktura feedu (solution.shopcode.cz):
 * <SHOP>
 *   <SHOPITEM id="251441">
 *     <n>Název produktu</n>                         ← název je v <n>, NE <PRODUCTNAME>
 *     <GUID>...</GUID>
 *     <DESCRIPTION><![CDATA[...]]></DESCRIPTION>
 *     <ITEM_TYPE>product|variant</ITEM_TYPE>
 *     <CATEGORIES>
 *       <CATEGORY id="22787">NOVINKY</CATEGORY>
 *       <DEFAULT_CATEGORY id="22918">aaa</DEFAULT_CATEGORY>
 *     </CATEGORIES>
 *     <IMAGES>
 *       <IMAGE description="">https://cdn.myshoptet.com/...</IMAGE>
 *     </IMAGES>
 *     <PARAMETERS>
 *       <PARAMETER><n>Barva skla</n><VALUE>šedá</VALUE></PARAMETER>
 *     </PARAMETERS>
 *     <TEXT_PROPERTIES>
 *       <TEXT_PROPERTY><n>Typ</n><VALUE>stojací</VALUE></TEXT_PROPERTY>
 *     </TEXT_PROPERTIES>
 *     <CURRENCY>CZK</CURRENCY>
 *     <PRICE_VAT>27</PRICE_VAT>
 *     <STOCK><AMOUNT>-6</AMOUNT></STOCK>
 *     <CODE>SKU</CODE>
 *     <ORIG_URL>https://...</ORIG_URL>
 *     <AVAILABILITY_OUT_OF_STOCK>...</AVAILABILITY_OUT_OF_STOCK>
 *     <VARIANTS>
 *       <VARIANT id="252560">
 *         <n>Název varianty</n>
 *         <CODE>SKU</CODE>
 *         <PRICE_VAT>...</PRICE_VAT>
 *         <STOCK><AMOUNT>5</AMOUNT></STOCK>
 *         <PARAMETERS>...</PARAMETERS>
 *       </VARIANT>
 *     </VARIANTS>
 *   </SHOPITEM>
 * </SHOP>
 */
class XmlParser
{
    private const PRODUCT_ELEMENT = 'SHOPITEM';

    /**
     * Streamovací iterátor — prochází XML a volá callback pro každý produkt.
     *
     * @param string        $filePath
     * @param callable      $callback  function(array $product, array $variants): void
     * @param callable|null $progress  function(int $processed): void
     * @return array{processed: int, errors: int, error_log: string[]}
     */
    public static function stream(
        string   $filePath,
        callable $callback,
        ?callable $progress = null   // explicitní nullable — opravuje Deprecated warning
    ): array {
        $reader = new \XMLReader();

        if (!$reader->open($filePath, 'UTF-8', LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \RuntimeException("Nelze otevřít XML soubor: {$filePath}");
        }

        $processed = 0;
        $errors    = 0;
        $errorLog  = [];

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) continue;
            if ($reader->localName  !== self::PRODUCT_ELEMENT) continue;

            try {
                $dom  = new \DOMDocument();
                $node = simplexml_import_dom($dom->importNode($reader->expand(), true));

                [$product, $variants] = self::parseProductNode($node);

                // ID je atribut na <SHOPITEM id="..."> — ne child element
                if (empty($product['shoptet_id'])) {
                    $errors++;
                    $errorLog[] = "SHOPITEM bez id atributu přeskočen";
                    continue;
                }

                $callback($product, $variants);
                $processed++;

                if ($progress && $processed % 100 === 0) {
                    $progress($processed);
                }

            } catch (\Throwable $e) {
                $errors++;
                $errorLog[] = "Chyba parsování: " . $e->getMessage();
                if (count($errorLog) > 100) array_shift($errorLog);
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
     * Parsuje jeden <SHOPITEM> node
     */
    private static function parseProductNode(\SimpleXMLElement $node): array
    {
        $attrs = $node->attributes();

        $product = [
            // ID je ATRIBUT <SHOPITEM id="...">
            'shoptet_id'   => isset($attrs['id']) ? (string)$attrs['id'] : null,

            // Název je v <n> (ne <PRODUCTNAME>)
            'name'         => self::text($node->n),

            // Popis — CDATA
            'description'  => self::text($node->DESCRIPTION),

            // Cena
            'price'        => self::decimal((string)($node->PRICE_VAT ?? '')),

            // Měna
            'currency'     => self::text($node->CURRENCY) ?: 'CZK',

            // SKU / kód
            'code'         => self::text($node->CODE),

            // URL produktu
            'url'          => self::text($node->ORIG_URL),

            // Dostupnost
            'availability' => self::text($node->AVAILABILITY_OUT_OF_STOCK),

            // Kategorie — <CATEGORIES><DEFAULT_CATEGORY>text</DEFAULT_CATEGORY>
            'category'     => self::parsePrimaryCategory($node),

            // Brand — není v marketing feedu standardně, může být v TEXT_PROPERTIES
            'brand'        => self::parseTextProperty($node, 'Výrobce')
                           ?: self::parseTextProperty($node, 'Značka')
                           ?: self::parseTextProperty($node, 'Brand'),

            // Sklad
            'stock'        => self::parseStock($node),

            // Obrázky — <IMAGES><IMAGE>url</IMAGE></IMAGES>
            'images'       => self::parseImages($node),

            // Parametry — <PARAMETERS><PARAMETER><n>název</n><VALUE>hodnota</VALUE></PARAMETER>
            // + <TEXT_PROPERTIES><TEXT_PROPERTY><n>...</n><VALUE>...</VALUE>
            'parameters'   => self::parseParameters($node),

            'xml_data'     => null,
        ];

        // Varianty — <VARIANTS><VARIANT id="...">
        $variants = self::parseVariants($node);

        return [$product, $variants];
    }

    /**
     * Kategorie — bereme DEFAULT_CATEGORY, fallback na první CATEGORY
     */
    private static function parsePrimaryCategory(\SimpleXMLElement $node): ?string
    {
        if (isset($node->CATEGORIES)) {
            // Zkusíme DEFAULT_CATEGORY
            if (isset($node->CATEGORIES->DEFAULT_CATEGORY)) {
                $cat = self::text($node->CATEGORIES->DEFAULT_CATEGORY);
                if ($cat) return $cat;
            }
            // Fallback na první CATEGORY
            foreach ($node->CATEGORIES->CATEGORY ?? [] as $cat) {
                $text = self::text($cat);
                if ($text) return $text;
            }
        }
        return null;
    }

    /**
     * Obrázky — <IMAGES><IMAGE>url</IMAGE></IMAGES>
     */
    private static function parseImages(\SimpleXMLElement $node): ?string
    {
        $images = [];

        if (isset($node->IMAGES)) {
            foreach ($node->IMAGES->IMAGE ?? [] as $img) {
                $url = trim((string)$img);
                if ($url) $images[] = $url;
            }
        }

        // Fallback na staré IMGURL tagy (jiné feedy)
        if (empty($images)) {
            foreach (['IMGURL', 'IMGURL_ALTERNATIVE'] as $tag) {
                if (isset($node->$tag)) {
                    $url = trim((string)$node->$tag);
                    if ($url && !in_array($url, $images)) $images[] = $url;
                }
            }
        }

        return !empty($images) ? json_encode($images, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Parametry — kombinuje <PARAMETERS> a <TEXT_PROPERTIES>
     */
    private static function parseParameters(\SimpleXMLElement $node): ?string
    {
        $params = [];

        // <PARAMETERS><PARAMETER><n>název</n><VALUE>hodnota</VALUE></PARAMETER>
        if (isset($node->PARAMETERS)) {
            foreach ($node->PARAMETERS->PARAMETER ?? [] as $p) {
                $name = self::text($p->n ?? $p->PARAM_NAME ?? null);
                $val  = self::text($p->VALUE ?? $p->VAL ?? null);
                if ($name) $params[$name] = $val ?? '';
            }
        }

        // <TEXT_PROPERTIES><TEXT_PROPERTY><n>název</n><VALUE>hodnota</VALUE>
        if (isset($node->TEXT_PROPERTIES)) {
            foreach ($node->TEXT_PROPERTIES->TEXT_PROPERTY ?? [] as $p) {
                $name = self::text($p->n ?? null);
                $val  = self::text($p->VALUE ?? null);
                if ($name) $params[$name] = $val ?? '';
            }
        }

        // Fallback — starší formát <PARAM><PARAM_NAME>/<VAL>
        if (empty($params) && isset($node->PARAM)) {
            foreach ($node->PARAM as $p) {
                $name = self::text($p->PARAM_NAME ?? null);
                $val  = self::text($p->VAL ?? null);
                if ($name) $params[$name] = $val ?? '';
            }
        }

        return !empty($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Sklad — <STOCK><AMOUNT>číslo</AMOUNT></STOCK>
     */
    private static function parseStock(\SimpleXMLElement $node): int
    {
        if (isset($node->STOCK->AMOUNT)) {
            return (int)(string)$node->STOCK->AMOUNT;
        }
        if (isset($node->STOCK_QUANTITY)) {
            return (int)(string)$node->STOCK_QUANTITY;
        }
        return 0;
    }

    /**
     * Varianty — <VARIANTS><VARIANT id="...">
     */
    private static function parseVariants(\SimpleXMLElement $node): array
    {
        $variants = [];

        if (!isset($node->VARIANTS)) return $variants;

        foreach ($node->VARIANTS->VARIANT ?? [] as $varNode) {
            $vAttrs = $varNode->attributes();
            $variantId = isset($vAttrs['id']) ? (string)$vAttrs['id'] : null;

            if (!$variantId) continue;

            $vParams = [];
            if (isset($varNode->PARAMETERS)) {
                foreach ($varNode->PARAMETERS->PARAMETER ?? [] as $p) {
                    $name = self::text($p->n ?? $p->PARAM_NAME ?? null);
                    $val  = self::text($p->VALUE ?? $p->VAL ?? null);
                    if ($name) $vParams[$name] = $val ?? '';
                }
            }

            $variants[] = [
                'shoptet_variant_id' => $variantId,
                'name'               => self::text($varNode->n ?? null),
                'code'               => self::text($varNode->CODE ?? null),
                'price'              => self::decimal((string)($varNode->PRICE_VAT ?? '')),
                'stock'              => isset($varNode->STOCK->AMOUNT)
                                        ? (int)(string)$varNode->STOCK->AMOUNT
                                        : 0,
                'parameters'         => !empty($vParams)
                                        ? json_encode($vParams, JSON_UNESCAPED_UNICODE)
                                        : null,
            ];
        }

        return $variants;
    }

    /**
     * Najde hodnotu v TEXT_PROPERTIES podle názvu
     */
    private static function parseTextProperty(\SimpleXMLElement $node, string $name): ?string
    {
        if (!isset($node->TEXT_PROPERTIES)) return null;
        foreach ($node->TEXT_PROPERTIES->TEXT_PROPERTY ?? [] as $p) {
            if (strcasecmp(self::text($p->n ?? null) ?? '', $name) === 0) {
                return self::text($p->VALUE ?? null);
            }
        }
        return null;
    }

    /**
     * Bezpečně přečte text z elementu (včetně CDATA)
     */
    private static function text(mixed $el): ?string
    {
        if ($el === null) return null;
        $str = trim((string)$el);
        return $str !== '' ? $str : null;
    }

    /**
     * Bezpečný převod na float
     */
    private static function decimal(string $val): ?float
    {
        $val = str_replace([' ', ','], ['', '.'], trim($val));
        return is_numeric($val) ? (float)$val : null;
    }
}
