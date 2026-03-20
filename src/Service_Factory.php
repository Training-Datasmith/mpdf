<?php

namespace Mpdf;

use Mpdf\Color\Color_Converter;
use Mpdf\Color\Color_Mode_Converter;
use Mpdf\Color\Color_Space_Restrictor;
use Mpdf\Css\Border_Merger;
use Mpdf\Css\Css_Merger;
use Mpdf\Css\Css_Parser;
use Mpdf\Css\Inline_Property_Converter;
use Mpdf\Css\Inline_Style_Parser;
use Mpdf\Css\Normalize_Properties;
use Mpdf\Css\Selector_Parser;
use Mpdf\Css\Shadow_Parser;
use Mpdf\File\Local_Content_Loader;
use Mpdf\Fonts\Font_Cache;
use Mpdf\Fonts\Font_File_Finder;
use Mpdf\Http\Curl_Http_Client;
use Mpdf\Http\Socket_Http_Client;
use Mpdf\Image\Image_Processor;
use Mpdf\Pdf\Protection;
use Mpdf\Pdf\Protection\Uniqid_Generator;
use Mpdf\Writer\Base_Writer;
use Mpdf\Writer\Background_Writer;
use Mpdf\Writer\Color_Writer;
use Mpdf\Writer\Bookmark_Writer;
use Mpdf\Writer\Font_Writer;
use Mpdf\Writer\Form_Writer;
use Mpdf\Writer\Image_Writer;
use Mpdf\Writer\Java_Script_Writer;
use Mpdf\Writer\Metadata_Writer;
use Mpdf\Writer\Optional_Content_Writer;
use Mpdf\Writer\Page_Writer;
use Mpdf\Writer\Resource_Writer;
use Psr\Log\Logger_Interface;
class Service_Factory
{
    /**
     * @var \Mpdf\Container\ContainerInterface|null
     */
    private $container;
    public function __construct($container = null)
    {
        $this->container = $container;
    }
    public function get_services(Mpdf $mpdf, Logger_Interface $logger, $config, $language_to_font, $script_to_language, $font_descriptor, $bmp, $direct_write, $wmf)
    {
        $size_converter = new Size_Converter($mpdf->dpi, $mpdf->default_font_size, $mpdf, $logger);
        $color_mode_converter = new Color_Mode_Converter();
        $color_space_restrictor = new Color_Space_Restrictor($mpdf, $color_mode_converter);
        $color_converter = new Color_Converter($mpdf, $color_mode_converter, $color_space_restrictor);
        $table_of_contents = new Table_Of_Contents($mpdf, $size_converter);
        $cache_base_path = $config['tempDir'] . '/mpdf';
        $cache = new Cache($cache_base_path, $config['cacheCleanupInterval']);
        $font_cache = new Font_Cache(new Cache($cache_base_path . '/ttfontdata', $config['cacheCleanupInterval']));
        $font_file_finder = new Font_File_Finder($config['fontDir']);
        if ($this->container && $this->container->has('httpClient')) {
            $http_client = $this->container->get('httpClient');
        } elseif (\function_exists('curl_init')) {
            $http_client = new Curl_Http_Client($mpdf, $logger);
        } else {
            $http_client = new Socket_Http_Client($logger);
        }
        $local_content_loader = $this->container && $this->container->has('localContentLoader') ? $this->container->get('localContentLoader') : new Local_Content_Loader();
        $asset_fetcher = $this->container && $this->container->has('assetFetcher') ? $this->container->get('assetFetcher') : new Asset_Fetcher($mpdf, $local_content_loader, $http_client, $logger);
        $normalize_properties = new Normalize_Properties($mpdf, $size_converter, $color_converter);
        $selector_parser = new Selector_Parser($mpdf);
        $inline_style_parser = new Inline_Style_Parser($normalize_properties);
        $inline_property_converter = new Inline_Property_Converter($color_converter);
        $border_merger = new Border_Merger();
        $css_parser = new Css_Parser($mpdf, $cache, $size_converter, $color_converter, $asset_fetcher);
        $css_merger = new Css_Merger($mpdf, $normalize_properties, $inline_style_parser, $selector_parser, $inline_property_converter, $color_converter, $border_merger);
        $css_manager = new Css_Manager($css_parser, $css_merger);
        $otl = new Otl($mpdf, $font_cache);
        $protection = new Protection(new Uniqid_Generator());
        $writer = new Base_Writer($mpdf, $protection);
        $gradient = new Gradient($mpdf, $size_converter, $color_converter, $writer);
        $form_writer = new Form_Writer($mpdf, $writer);
        $form = new Form($mpdf, $otl, $color_converter, $writer, $form_writer);
        $hyphenator = new Hyphenator($mpdf);
        $image_processor = new Image_Processor($mpdf, $otl, $css_manager, $size_converter, $color_converter, $color_mode_converter, $cache, $language_to_font, $script_to_language, $asset_fetcher, $logger);
        $tag = new Tag($mpdf, $cache, $css_manager, $form, $otl, $table_of_contents, $size_converter, $color_converter, $image_processor, $language_to_font);
        $font_writer = new Font_Writer($mpdf, $writer, $font_cache, $font_descriptor);
        $metadata_writer = new Metadata_Writer($mpdf, $writer, $form, $protection, $logger);
        $image_writer = new Image_Writer($mpdf, $writer);
        $page_writer = new Page_Writer($mpdf, $form, $writer, $metadata_writer);
        $bookmark_writer = new Bookmark_Writer($mpdf, $writer);
        $optional_content_writer = new Optional_Content_Writer($mpdf, $writer);
        $color_writer = new Color_Writer($mpdf, $writer);
        $background_writer = new Background_Writer($mpdf, $writer);
        $java_script_writer = new Java_Script_Writer($mpdf, $writer);
        $resource_writer = new Resource_Writer($mpdf, $writer, $color_writer, $font_writer, $image_writer, $form_writer, $optional_content_writer, $background_writer, $bookmark_writer, $metadata_writer, $java_script_writer, $logger);
        return ['otl' => $otl, 'bmp' => $bmp, 'cache' => $cache, 'cssManager' => $css_manager, 'directWrite' => $direct_write, 'fontCache' => $font_cache, 'fontFileFinder' => $font_file_finder, 'form' => $form, 'gradient' => $gradient, 'tableOfContents' => $table_of_contents, 'tag' => $tag, 'wmf' => $wmf, 'sizeConverter' => $size_converter, 'colorConverter' => $color_converter, 'hyphenator' => $hyphenator, 'localContentLoader' => $local_content_loader, 'httpClient' => $http_client, 'assetFetcher' => $asset_fetcher, 'imageProcessor' => $image_processor, 'protection' => $protection, 'languageToFont' => $language_to_font, 'scriptToLanguage' => $script_to_language, 'writer' => $writer, 'fontWriter' => $font_writer, 'metadataWriter' => $metadata_writer, 'imageWriter' => $image_writer, 'formWriter' => $form_writer, 'pageWriter' => $page_writer, 'bookmarkWriter' => $bookmark_writer, 'optionalContentWriter' => $optional_content_writer, 'colorWriter' => $color_writer, 'backgroundWriter' => $background_writer, 'javaScriptWriter' => $java_script_writer, 'resourceWriter' => $resource_writer];
    }
    public function get_service_ids()
    {
        return ['otl', 'bmp', 'cache', 'cssManager', 'directWrite', 'fontCache', 'fontFileFinder', 'form', 'gradient', 'tableOfContents', 'tag', 'wmf', 'sizeConverter', 'colorConverter', 'hyphenator', 'localContentLoader', 'httpClient', 'assetFetcher', 'imageProcessor', 'protection', 'languageToFont', 'scriptToLanguage', 'writer', 'fontWriter', 'metadataWriter', 'imageWriter', 'formWriter', 'pageWriter', 'bookmarkWriter', 'optionalContentWriter', 'colorWriter', 'backgroundWriter', 'javaScriptWriter', 'resourceWriter'];
    }
}