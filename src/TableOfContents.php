<?php

namespace Mpdf;

use Mpdf\Utils\Arrays;
use Deep_Copy\Deep_Copy;
class Table_Of_Contents
{
    private $mpdf;
    private $size_converter;
    var $_toc;
    var $to_cmark;
    var $to_coutdent;
    // mPDF 5.6.31
    var $to_cpre_html;
    var $to_cpost_html;
    var $to_cbookmark_text;
    var $to_cuse_paging;
    var $to_cuse_linking;
    var $to_corientation;
    var $TOC_margin_left;
    var $TOC_margin_right;
    var $TOC_margin_top;
    var $TOC_margin_bottom;
    var $TOC_margin_header;
    var $TOC_margin_footer;
    var $TOC_odd_header_name;
    var $TOC_even_header_name;
    var $TOC_odd_footer_name;
    var $TOC_even_footer_name;
    var $TOC_odd_header_value;
    var $TOC_even_header_value;
    var $TOC_odd_footer_value;
    var $TOC_even_footer_value;
    var $TOC_page_selector;
    var $TOC_resetpagenum;
    // mPDF 6
    var $TOC_pagenumstyle;
    // mPDF 6
    var $TOC_suppress;
    // mPDF 6
    var $to_csheetsize;
    var $m_TOC;
    /**
     * @var bool Determine if the TOC should be cloned to calculate the correct page numbers
     */
    protected $toc_toc_paint_begun = false;
    public function __construct(Mpdf $mpdf, Size_Converter $size_converter)
    {
        $this->mpdf = $mpdf;
        $this->size_converter = $size_converter;
        $this->_toc = [];
        $this->to_cmark = 0;
        $this->m_TOC = [];
    }
    /**
     * Mark the TOC Paint as having begun
     */
    public function begin_toc_paint()
    {
        $this->toc_toc_paint_begun = true;
    }
    public function to_cpagebreak($tocfont = '', $tocfontsize = '', $tocindent = '', $to_cuse_paging = true, $to_cuse_linking = '', $toc_orientation = '', $toc_mgl = '', $toc_mgr = '', $toc_mgt = '', $toc_mgb = '', $toc_mgh = '', $toc_mgf = '', $toc_ohname = '', $toc_ehname = '', $toc_ofname = '', $toc_efname = '', $toc_ohvalue = 0, $toc_ehvalue = 0, $toc_ofvalue = 0, $toc_efvalue = 0, $toc_pre_html = '', $toc_post_html = '', $toc_bookmark_text = '', $resetpagenum = '', $pagenumstyle = '', $suppress = '', $orientation = '', $mgl = '', $mgr = '', $mgt = '', $mgb = '', $mgh = '', $mgf = '', $ohname = '', $ehname = '', $ofname = '', $efname = '', $ohvalue = 0, $ehvalue = 0, $ofvalue = 0, $efvalue = 0, $toc_id = 0, $pagesel = '', $toc_pagesel = '', $sheetsize = '', $toc_sheetsize = '', $tocoutdent = '', $toc_resetpagenum = '', $toc_pagenumstyle = '', $toc_suppress = '')
    {
        if (strtoupper($toc_id) == 'ALL') {
            $toc_id = '_mpdf_all';
        } elseif (!$toc_id) {
            $toc_id = 0;
        } else {
            $toc_id = strtolower($toc_id);
        }
        if ($to_cuse_paging === false || strtolower($to_cuse_paging) == "off" || $to_cuse_paging === 0 || $to_cuse_paging === "0" || $to_cuse_paging === "") {
            $to_cuse_paging = false;
        } else {
            $to_cuse_paging = true;
        }
        if (!$to_cuse_linking) {
            $to_cuse_linking = false;
        }
        if ($toc_id) {
            $this->m_TOC[$toc_id]['TOCmark'] = $this->mpdf->page;
            $this->m_TOC[$toc_id]['TOCoutdent'] = $tocoutdent;
            $this->m_TOC[$toc_id]['TOCorientation'] = $toc_orientation;
            $this->m_TOC[$toc_id]['TOCuseLinking'] = $to_cuse_linking;
            $this->m_TOC[$toc_id]['TOCusePaging'] = $to_cuse_paging;
            if ($toc_pre_html) {
                $this->m_TOC[$toc_id]['TOCpreHTML'] = $toc_pre_html;
            }
            if ($toc_post_html) {
                $this->m_TOC[$toc_id]['TOCpostHTML'] = $toc_post_html;
            }
            if ($toc_bookmark_text) {
                $this->m_TOC[$toc_id]['TOCbookmarkText'] = $toc_bookmark_text;
            }
            $this->m_TOC[$toc_id]['TOC_margin_left'] = $toc_mgl;
            $this->m_TOC[$toc_id]['TOC_margin_right'] = $toc_mgr;
            $this->m_TOC[$toc_id]['TOC_margin_top'] = $toc_mgt;
            $this->m_TOC[$toc_id]['TOC_margin_bottom'] = $toc_mgb;
            $this->m_TOC[$toc_id]['TOC_margin_header'] = $toc_mgh;
            $this->m_TOC[$toc_id]['TOC_margin_footer'] = $toc_mgf;
            $this->m_TOC[$toc_id]['TOC_odd_header_name'] = $toc_ohname;
            $this->m_TOC[$toc_id]['TOC_even_header_name'] = $toc_ehname;
            $this->m_TOC[$toc_id]['TOC_odd_footer_name'] = $toc_ofname;
            $this->m_TOC[$toc_id]['TOC_even_footer_name'] = $toc_efname;
            $this->m_TOC[$toc_id]['TOC_odd_header_value'] = $toc_ohvalue;
            $this->m_TOC[$toc_id]['TOC_even_header_value'] = $toc_ehvalue;
            $this->m_TOC[$toc_id]['TOC_odd_footer_value'] = $toc_ofvalue;
            $this->m_TOC[$toc_id]['TOC_even_footer_value'] = $toc_efvalue;
            $this->m_TOC[$toc_id]['TOC_page_selector'] = $toc_pagesel;
            $this->m_TOC[$toc_id]['TOC_resetpagenum'] = $toc_resetpagenum;
            // mPDF 6
            $this->m_TOC[$toc_id]['TOC_pagenumstyle'] = $toc_pagenumstyle;
            // mPDF 6
            $this->m_TOC[$toc_id]['TOC_suppress'] = $toc_suppress;
            // mPDF 6
            $this->m_TOC[$toc_id]['TOCsheetsize'] = $toc_sheetsize;
        } else {
            $this->to_cmark = $this->mpdf->page;
            $this->to_coutdent = $tocoutdent;
            $this->to_corientation = $toc_orientation;
            $this->to_cuse_linking = $to_cuse_linking;
            $this->to_cuse_paging = $to_cuse_paging;
            if ($toc_pre_html) {
                $this->to_cpre_html = $toc_pre_html;
            }
            if ($toc_post_html) {
                $this->to_cpost_html = $toc_post_html;
            }
            if ($toc_bookmark_text) {
                $this->to_cbookmark_text = $toc_bookmark_text;
            }
            $this->TOC_margin_left = $toc_mgl;
            $this->TOC_margin_right = $toc_mgr;
            $this->TOC_margin_top = $toc_mgt;
            $this->TOC_margin_bottom = $toc_mgb;
            $this->TOC_margin_header = $toc_mgh;
            $this->TOC_margin_footer = $toc_mgf;
            $this->TOC_odd_header_name = $toc_ohname;
            $this->TOC_even_header_name = $toc_ehname;
            $this->TOC_odd_footer_name = $toc_ofname;
            $this->TOC_even_footer_name = $toc_efname;
            $this->TOC_odd_header_value = $toc_ohvalue;
            $this->TOC_even_header_value = $toc_ehvalue;
            $this->TOC_odd_footer_value = $toc_ofvalue;
            $this->TOC_even_footer_value = $toc_efvalue;
            $this->TOC_page_selector = $toc_pagesel;
            $this->TOC_resetpagenum = $toc_resetpagenum;
            // mPDF 6
            $this->TOC_pagenumstyle = $toc_pagenumstyle;
            // mPDF 6
            $this->TOC_suppress = $toc_suppress;
            // mPDF 6
            $this->to_csheetsize = $toc_sheetsize;
        }
    }
    /**
     * Initiate, and Mark a place for the Table of Contents to be inserted
     */
    public function TOC($tocfont = '', $tocfontsize = 0, $tocindent = 0, $resetpagenum = '', $pagenumstyle = '', $suppress = '', $toc_orientation = '', $to_cuse_paging = true, $to_cuse_linking = false, $toc_id = 0, $tocoutdent = '', $toc_resetpagenum = '', $toc_pagenumstyle = '', $toc_suppress = '')
    {
        if (strtoupper($toc_id) == 'ALL') {
            $toc_id = '_mpdf_all';
        } elseif (!$toc_id) {
            $toc_id = 0;
        } else {
            $toc_id = strtolower($toc_id);
        }
        // To use odd and even pages
        // Cannot start table of contents on an even page
        if ($this->mpdf->mirror_margins && $this->mpdf->page % 2 == 0) {
            // EVEN
            if ($this->mpdf->col_active) {
                if (count($this->mpdf->columnbuffer)) {
                    $this->mpdf->printcolumnbuffer();
                }
            }
            $this->mpdf->add_page($this->mpdf->cur_orientation, '', $resetpagenum, $pagenumstyle, $suppress);
        } else {
            $this->mpdf->page_num_substitutions[] = ['from' => $this->mpdf->page, 'reset' => $resetpagenum, 'type' => $pagenumstyle, 'suppress' => $suppress];
        }
        if ($toc_id) {
            $this->m_TOC[$toc_id]['TOCmark'] = $this->mpdf->page;
            $this->m_TOC[$toc_id]['TOCoutdent'] = $tocoutdent;
            $this->m_TOC[$toc_id]['TOCorientation'] = $toc_orientation;
            $this->m_TOC[$toc_id]['TOCuseLinking'] = $to_cuse_linking;
            $this->m_TOC[$toc_id]['TOCusePaging'] = $to_cuse_paging;
            $this->m_TOC[$toc_id]['TOC_resetpagenum'] = $toc_resetpagenum;
            // mPDF 6
            $this->m_TOC[$toc_id]['TOC_pagenumstyle'] = $toc_pagenumstyle;
            // mPDF 6
            $this->m_TOC[$toc_id]['TOC_suppress'] = $toc_suppress;
            // mPDF 6
        } else {
            $this->to_cmark = $this->mpdf->page;
            $this->to_coutdent = $tocoutdent;
            $this->to_corientation = $toc_orientation;
            $this->to_cuse_linking = $to_cuse_linking;
            $this->to_cuse_paging = $to_cuse_paging;
            $this->TOC_resetpagenum = $toc_resetpagenum;
            // mPDF 6
            $this->TOC_pagenumstyle = $toc_pagenumstyle;
            // mPDF 6
            $this->TOC_suppress = $toc_suppress;
            // mPDF 6
        }
    }
    public function insert_toc()
    {
        /*
         * Fix the TOC page numbering problem
         *
         * To do this, the current class is deep cloned and then the TOC functionality run. The correct page
         * numbers are calculated when the TOC pages are moved into position in the cloned object (see Mpdf::MovePages).
         * It's then a matter of copying the correct page numbers to the original object and letting the TOC functionality
         * run as per normal.
         *
         * See https://github.com/mpdf/mpdf/issues/642
         */
        if (!$this->toc_toc_paint_begun) {
            $copier = new Deep_Copy(true);
            $toc_class_clone = $copier->copy($this);
            $toc_class_clone->begin_toc_paint();
            $toc_class_clone->insert_toc();
            $this->_toc = $toc_class_clone->_toc;
        }
        $notocs = 0;
        if ($this->to_cmark) {
            $notocs = 1;
        }
        $notocs += count($this->m_TOC);
        if ($notocs == 0) {
            return;
        }
        if (count($this->m_TOC)) {
            reset($this->m_TOC);
        }
        $added_toc_pages = 0;
        if ($this->mpdf->col_active) {
            $this->mpdf->set_columns(0);
        }
        if ($this->mpdf->mirror_margins && $this->mpdf->page % 2 == 1) {
            // ODD
            $this->mpdf->add_page($this->mpdf->cur_orientation);
            $extrapage = true;
        } else {
            $extrapage = false;
        }
        for ($toci = 0; $toci < $notocs; $toci++) {
            if ($toci == 0 && $this->to_cmark) {
                $toc_id = 0;
                $toc_page = $this->to_cmark;
                $tocoutdent = $this->to_coutdent;
                $toc_orientation = $this->to_corientation;
                $to_cuse_linking = $this->to_cuse_linking;
                $to_cuse_paging = $this->to_cuse_paging;
                $toc_pre_html = $this->to_cpre_html;
                $toc_post_html = $this->to_cpost_html;
                $toc_bookmark_text = $this->to_cbookmark_text;
                $toc_mgl = $this->TOC_margin_left;
                $toc_mgr = $this->TOC_margin_right;
                $toc_mgt = $this->TOC_margin_top;
                $toc_mgb = $this->TOC_margin_bottom;
                $toc_mgh = $this->TOC_margin_header;
                $toc_mgf = $this->TOC_margin_footer;
                $toc_ohname = $this->TOC_odd_header_name;
                $toc_ehname = $this->TOC_even_header_name;
                $toc_ofname = $this->TOC_odd_footer_name;
                $toc_efname = $this->TOC_even_footer_name;
                $toc_ohvalue = $this->TOC_odd_header_value;
                $toc_ehvalue = $this->TOC_even_header_value;
                $toc_ofvalue = $this->TOC_odd_footer_value;
                $toc_efvalue = $this->TOC_even_footer_value;
                $toc_page_selector = $this->TOC_page_selector;
                $toc_resetpagenum = $this->TOC_resetpagenum;
                // mPDF 6
                $toc_pagenumstyle = $this->TOC_pagenumstyle;
                // mPDF 6
                $toc_suppress = $this->TOC_suppress;
                // mPDF 6
                $toc_sheet_size = isset($this->to_csheetsize) ? $this->to_csheetsize : '';
            } else {
                $arr = current($this->m_TOC);
                $toc_id = key($this->m_TOC);
                $toc_page = $this->m_TOC[$toc_id]['TOCmark'];
                $tocoutdent = $this->m_TOC[$toc_id]['TOCoutdent'];
                $toc_orientation = $this->m_TOC[$toc_id]['TOCorientation'];
                $to_cuse_linking = $this->m_TOC[$toc_id]['TOCuseLinking'];
                $to_cuse_paging = $this->m_TOC[$toc_id]['TOCusePaging'];
                if (isset($this->m_TOC[$toc_id]['TOCpreHTML'])) {
                    $toc_pre_html = $this->m_TOC[$toc_id]['TOCpreHTML'];
                } else {
                    $toc_pre_html = '';
                }
                if (isset($this->m_TOC[$toc_id]['TOCpostHTML'])) {
                    $toc_post_html = $this->m_TOC[$toc_id]['TOCpostHTML'];
                } else {
                    $toc_post_html = '';
                }
                if (isset($this->m_TOC[$toc_id]['TOCbookmarkText'])) {
                    $toc_bookmark_text = $this->m_TOC[$toc_id]['TOCbookmarkText'];
                } else {
                    $toc_bookmark_text = '';
                }
                // *BOOKMARKS*
                $toc_mgl = $this->m_TOC[$toc_id]['TOC_margin_left'];
                $toc_mgr = $this->m_TOC[$toc_id]['TOC_margin_right'];
                $toc_mgt = $this->m_TOC[$toc_id]['TOC_margin_top'];
                $toc_mgb = $this->m_TOC[$toc_id]['TOC_margin_bottom'];
                $toc_mgh = $this->m_TOC[$toc_id]['TOC_margin_header'];
                $toc_mgf = $this->m_TOC[$toc_id]['TOC_margin_footer'];
                $toc_ohname = $this->m_TOC[$toc_id]['TOC_odd_header_name'];
                $toc_ehname = $this->m_TOC[$toc_id]['TOC_even_header_name'];
                $toc_ofname = $this->m_TOC[$toc_id]['TOC_odd_footer_name'];
                $toc_efname = $this->m_TOC[$toc_id]['TOC_even_footer_name'];
                $toc_ohvalue = $this->m_TOC[$toc_id]['TOC_odd_header_value'];
                $toc_ehvalue = $this->m_TOC[$toc_id]['TOC_even_header_value'];
                $toc_ofvalue = $this->m_TOC[$toc_id]['TOC_odd_footer_value'];
                $toc_efvalue = $this->m_TOC[$toc_id]['TOC_even_footer_value'];
                $toc_page_selector = $this->m_TOC[$toc_id]['TOC_page_selector'];
                $toc_resetpagenum = $this->m_TOC[$toc_id]['TOC_resetpagenum'];
                // mPDF 6
                $toc_pagenumstyle = $this->m_TOC[$toc_id]['TOC_pagenumstyle'];
                // mPDF 6
                $toc_suppress = $this->m_TOC[$toc_id]['TOC_suppress'];
                // mPDF 6
                $toc_sheet_size = isset($this->m_TOC[$toc_id]['TOCsheetsize']) ? $this->m_TOC[$toc_id]['TOCsheetsize'] : '';
                next($this->m_TOC);
            }
            // mPDF 5.6.31
            if (!$toc_orientation) {
                $toc_orientation = $this->mpdf->def_orientation;
            }
            //  mPDF 6 number style and suppress now picked up from section preceding ToC
            list($tp_pagenumstyle, $tp_suppress, $tp_reset) = $this->mpdf->doc_page_settings($toc_page - 1);
            if ($toc_resetpagenum) {
                $tp_reset = $toc_resetpagenum;
                // mPDF 6
            }
            if ($toc_pagenumstyle) {
                $tp_pagenumstyle = $toc_pagenumstyle;
                // mPDF 6
            }
            if ($toc_suppress || $toc_suppress === '0') {
                $tp_suppress = $toc_suppress;
                // mPDF 6
            }
            $this->mpdf->add_page($toc_orientation, '', $tp_reset, $tp_pagenumstyle, $tp_suppress, $toc_mgl, $toc_mgr, $toc_mgt, $toc_mgb, $toc_mgh, $toc_mgf, $toc_ohname, $toc_ehname, $toc_ofname, $toc_efname, $toc_ohvalue, $toc_ehvalue, $toc_ofvalue, $toc_efvalue, $toc_page_selector, $toc_sheet_size);
            // mPDF 6
            $this->mpdf->writing_to_c = true;
            // mPDF 5.6.38
            /*
             * Ensure the TOC Page Number Style doesn't effect the TOC Numbering (added automatically in `AddPage()` above)
             * Ensure the page numbers show in the TOC when the 'suppress' setting is enabled
             * @see https://github.com/mpdf/mpdf/issues/792
             * @see https://github.com/mpdf/mpdf/issues/777
             */
            if (isset($toc_class_clone)) {
                $this->mpdf->page_num_substitutions = array_map(function ($sub) {
                    $sub['suppress'] = '';
                    return $sub;
                }, $toc_class_clone->mpdf->page_num_substitutions);
            }
            // mPDF 5.6.31
            $tocstart = count($this->mpdf->pages);
            if (isset($toc_pre_html) && $toc_pre_html) {
                $this->mpdf->write_html($toc_pre_html);
            }
            // mPDF 5.6.19
            $html = '<div class="mpdf_toc" id="mpdf_toc_' . $toc_id . '">';
            foreach ($this->_toc as $t) {
                if ($t['toc_id'] === '_mpdf_all' || $t['toc_id'] === $toc_id) {
                    $html .= '<div class="mpdf_toc_level_' . $t['l'] . '">';
                    if ($to_cuse_linking) {
                        $html .= '<a class="mpdf_toc_a" href="#__mpdfinternallink_' . $t['link'] . '">';
                    }
                    $html .= '<span class="mpdf_toc_t_level_' . $t['l'] . '">' . $t['t'] . '</span>';
                    if ($to_cuse_linking) {
                        $html .= '</a>';
                    }
                    if (!$tocoutdent) {
                        $tocoutdent = '0';
                    }
                    if ($to_cuse_paging) {
                        $html .= ' <dottab outdent="' . $tocoutdent . '" /> ';
                        if ($to_cuse_linking) {
                            $html .= '<a class="mpdf_toc_a" href="#__mpdfinternallink_' . $t['link'] . '">';
                        }
                        $html .= '<span class="mpdf_toc_p_level_' . $t['l'] . '">' . $this->mpdf->doc_page_num($t['p']) . '</span>';
                        if ($to_cuse_linking) {
                            $html .= '</a>';
                        }
                    }
                    $html .= '</div>';
                }
            }
            $html .= '</div>';
            $this->mpdf->write_html($html);
            if (isset($toc_post_html) && $toc_post_html) {
                $this->mpdf->write_html($toc_post_html);
            }
            $this->mpdf->writing_to_c = false;
            // mPDF 5.6.38
            $this->mpdf->add_page($toc_orientation, 'E');
            $n_toc = $this->mpdf->page - $tocstart + 1;
            if ($toci == 0 && $this->to_cmark) {
                $TOC_start = $tocstart;
                $TOC_end = $this->mpdf->page;
                $TOC_npages = $n_toc;
            } else {
                $this->m_TOC[$toc_id]['start'] = $tocstart;
                $this->m_TOC[$toc_id]['end'] = $this->mpdf->page;
                $this->m_TOC[$toc_id]['npages'] = $n_toc;
            }
        }
        $s = '';
        $s .= $this->mpdf->print_body_backgrounds();
        $s .= $this->mpdf->print_page_backgrounds();
        $this->mpdf->pages[$this->mpdf->page] = preg_replace('/(___BACKGROUND___PATTERNS' . $this->mpdf->uniqstr . ')/', "\n" . $s . "\n" . '\1', $this->mpdf->pages[$this->mpdf->page]);
        $this->mpdf->page_backgrounds = [];
        //Page footer
        $this->mpdf->in_footer = true;
        $this->mpdf->Footer();
        $this->mpdf->in_footer = false;
        // 2nd time through to move pages etc.
        $added_toc_pages = 0;
        if (count($this->m_TOC)) {
            reset($this->m_TOC);
        }
        for ($toci = 0; $toci < $notocs; $toci++) {
            if ($toci == 0 && $this->to_cmark) {
                $toc_id = 0;
                $toc_page = $this->to_cmark + $added_toc_pages;
                $toc_orientation = $this->to_corientation;
                $to_cuse_linking = $this->to_cuse_linking;
                $to_cuse_paging = $this->to_cuse_paging;
                $toc_bookmark_text = $this->to_cbookmark_text;
                // *BOOKMARKS*
                $tocstart = $TOC_start;
                $tocend = $n = $TOC_end;
                $n_toc = $TOC_npages;
            } else {
                $arr = current($this->m_TOC);
                $toc_id = key($this->m_TOC);
                $toc_page = $this->m_TOC[$toc_id]['TOCmark'] + $added_toc_pages;
                $toc_orientation = $this->m_TOC[$toc_id]['TOCorientation'];
                $to_cuse_linking = $this->m_TOC[$toc_id]['TOCuseLinking'];
                $to_cuse_paging = $this->m_TOC[$toc_id]['TOCusePaging'];
                $toc_bookmark_text = Arrays::get($this->m_TOC[$toc_id], 'TOCbookmarkText', null);
                // *BOOKMARKS*
                $tocstart = $this->m_TOC[$toc_id]['start'];
                $tocend = $n = $this->m_TOC[$toc_id]['end'];
                $n_toc = $this->m_TOC[$toc_id]['npages'];
                next($this->m_TOC);
            }
            // Now pages moved
            $added_toc_pages += $n_toc;
            $this->mpdf->move_pages($toc_page, $tocstart, $tocend);
            $this->mpdf->pgs_ins[$toc_page] = $tocend - $tocstart + 1;
            /* -- BOOKMARKS -- */
            // Insert new Bookmark for Bookmark
            if ($toc_bookmark_text) {
                $insert = -1;
                foreach ($this->mpdf->b_moutlines as $i => $o) {
                    if ($o['p'] < $toc_page) {
                        // i.e. before point of insertion
                        $insert = $i;
                    }
                }
                $txt = $this->mpdf->purify_utf8_text($toc_bookmark_text);
                if ($this->mpdf->text_input_as_HTML) {
                    $txt = $this->mpdf->all_entities_to_utf8($txt);
                }
                $new_bookmark[0] = ['t' => $txt, 'l' => 0, 'y' => 0, 'p' => $toc_page];
                array_splice($this->mpdf->b_moutlines, $insert + 1, 0, $new_bookmark);
            }
            /* -- END BOOKMARKS -- */
        }
        // Delete empty page that was inserted earlier
        if ($extrapage) {
            unset($this->mpdf->pages[count($this->mpdf->pages)]);
            $this->mpdf->page--;
            // Reset page pointer
        }
        /* Fix the over adjustment of the TOC and Page Substitutions values */
        if (isset($toc_class_clone)) {
            $this->_toc = $toc_class_clone->_toc;
            $this->mpdf->page_num_substitutions = $toc_class_clone->mpdf->page_num_substitutions;
            unset($toc_class_clone);
        }
    }
    public function open_tag_toc($attr)
    {
        if (isset($attr['OUTDENT']) && $attr['OUTDENT']) {
            $tocoutdent = $attr['OUTDENT'];
        } else {
            $tocoutdent = '';
        }
        // mPDF 5.6.19
        if (isset($attr['RESETPAGENUM']) && $attr['RESETPAGENUM']) {
            $resetpagenum = $attr['RESETPAGENUM'];
        } else {
            $resetpagenum = '';
        }
        if (isset($attr['PAGENUMSTYLE']) && $attr['PAGENUMSTYLE']) {
            $pagenumstyle = $attr['PAGENUMSTYLE'];
        } else {
            $pagenumstyle = '';
        }
        if (isset($attr['SUPPRESS']) && $attr['SUPPRESS']) {
            $suppress = $attr['SUPPRESS'];
        } else {
            $suppress = '';
        }
        if (isset($attr['TOC-ORIENTATION']) && $attr['TOC-ORIENTATION']) {
            $toc_orientation = $attr['TOC-ORIENTATION'];
        } else {
            $toc_orientation = '';
        }
        if (isset($attr['PAGING']) && (strtoupper($attr['PAGING']) == 'OFF' || $attr['PAGING'] === '0')) {
            $paging = false;
        } else {
            $paging = true;
        }
        if (isset($attr['LINKS']) && (strtoupper($attr['LINKS']) == 'ON' || $attr['LINKS'] == 1)) {
            $links = true;
        } else {
            $links = false;
        }
        if (isset($attr['NAME']) && $attr['NAME']) {
            $toc_id = strtolower($attr['NAME']);
        } else {
            $toc_id = 0;
        }
        $this->TOC('', 0, 0, $resetpagenum, $pagenumstyle, $suppress, $toc_orientation, $paging, $links, $toc_id, $tocoutdent);
        // mPDF 5.6.19 5.6.31
    }
    public function open_tag_tocpagebreak($attr)
    {
        if (isset($attr['NAME']) && $attr['NAME']) {
            $toc_id = strtolower($attr['NAME']);
        } else {
            $toc_id = 0;
        }
        if ($toc_id) {
            if (isset($attr['OUTDENT']) && $attr['OUTDENT']) {
                $this->m_TOC[$toc_id]['TOCoutdent'] = $attr['OUTDENT'];
            } else {
                $this->m_TOC[$toc_id]['TOCoutdent'] = '';
            }
            // mPDF 5.6.19
            if (isset($attr['TOC-ORIENTATION']) && $attr['TOC-ORIENTATION']) {
                $this->m_TOC[$toc_id]['TOCorientation'] = $attr['TOC-ORIENTATION'];
            } else {
                $this->m_TOC[$toc_id]['TOCorientation'] = '';
            }
            if (isset($attr['PAGING']) && (strtoupper($attr['PAGING']) == 'OFF' || $attr['PAGING'] === '0')) {
                $this->m_TOC[$toc_id]['TOCusePaging'] = false;
            } else {
                $this->m_TOC[$toc_id]['TOCusePaging'] = true;
            }
            if (isset($attr['LINKS']) && (strtoupper($attr['LINKS']) == 'ON' || $attr['LINKS'] == 1)) {
                $this->m_TOC[$toc_id]['TOCuseLinking'] = true;
            } else {
                $this->m_TOC[$toc_id]['TOCuseLinking'] = false;
            }
            $this->m_TOC[$toc_id]['TOC_margin_left'] = $this->m_TOC[$toc_id]['TOC_margin_right'] = $this->m_TOC[$toc_id]['TOC_margin_top'] = $this->m_TOC[$toc_id]['TOC_margin_bottom'] = $this->m_TOC[$toc_id]['TOC_margin_header'] = $this->m_TOC[$toc_id]['TOC_margin_footer'] = '';
            if (isset($attr['TOC-MARGIN-RIGHT'])) {
                $this->m_TOC[$toc_id]['TOC_margin_right'] = $this->size_converter->convert($attr['TOC-MARGIN-RIGHT'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-LEFT'])) {
                $this->m_TOC[$toc_id]['TOC_margin_left'] = $this->size_converter->convert($attr['TOC-MARGIN-LEFT'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-TOP'])) {
                $this->m_TOC[$toc_id]['TOC_margin_top'] = $this->size_converter->convert($attr['TOC-MARGIN-TOP'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-BOTTOM'])) {
                $this->m_TOC[$toc_id]['TOC_margin_bottom'] = $this->size_converter->convert($attr['TOC-MARGIN-BOTTOM'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-HEADER'])) {
                $this->m_TOC[$toc_id]['TOC_margin_header'] = $this->size_converter->convert($attr['TOC-MARGIN-HEADER'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-FOOTER'])) {
                $this->m_TOC[$toc_id]['TOC_margin_footer'] = $this->size_converter->convert($attr['TOC-MARGIN-FOOTER'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            $this->m_TOC[$toc_id]['TOC_odd_header_name'] = $this->m_TOC[$toc_id]['TOC_even_header_name'] = $this->m_TOC[$toc_id]['TOC_odd_footer_name'] = $this->m_TOC[$toc_id]['TOC_even_footer_name'] = '';
            if (isset($attr['TOC-ODD-HEADER-NAME']) && $attr['TOC-ODD-HEADER-NAME']) {
                $this->m_TOC[$toc_id]['TOC_odd_header_name'] = $attr['TOC-ODD-HEADER-NAME'];
            }
            if (isset($attr['TOC-EVEN-HEADER-NAME']) && $attr['TOC-EVEN-HEADER-NAME']) {
                $this->m_TOC[$toc_id]['TOC_even_header_name'] = $attr['TOC-EVEN-HEADER-NAME'];
            }
            if (isset($attr['TOC-ODD-FOOTER-NAME']) && $attr['TOC-ODD-FOOTER-NAME']) {
                $this->m_TOC[$toc_id]['TOC_odd_footer_name'] = $attr['TOC-ODD-FOOTER-NAME'];
            }
            if (isset($attr['TOC-EVEN-FOOTER-NAME']) && $attr['TOC-EVEN-FOOTER-NAME']) {
                $this->m_TOC[$toc_id]['TOC_even_footer_name'] = $attr['TOC-EVEN-FOOTER-NAME'];
            }
            $this->m_TOC[$toc_id]['TOC_odd_header_value'] = $this->m_TOC[$toc_id]['TOC_even_header_value'] = $this->m_TOC[$toc_id]['TOC_odd_footer_value'] = $this->m_TOC[$toc_id]['TOC_even_footer_value'] = 0;
            if (isset($attr['TOC-ODD-HEADER-VALUE']) && ($attr['TOC-ODD-HEADER-VALUE'] == '1' || strtoupper($attr['TOC-ODD-HEADER-VALUE']) == 'ON')) {
                $this->m_TOC[$toc_id]['TOC_odd_header_value'] = 1;
            } elseif (isset($attr['TOC-ODD-HEADER-VALUE']) && ($attr['TOC-ODD-HEADER-VALUE'] == '-1' || strtoupper($attr['TOC-ODD-HEADER-VALUE']) == 'OFF')) {
                $this->m_TOC[$toc_id]['TOC_odd_header_value'] = -1;
            }
            if (isset($attr['TOC-EVEN-HEADER-VALUE']) && ($attr['TOC-EVEN-HEADER-VALUE'] == '1' || strtoupper($attr['TOC-EVEN-HEADER-VALUE']) == 'ON')) {
                $this->m_TOC[$toc_id]['TOC_even_header_value'] = 1;
            } elseif (isset($attr['TOC-EVEN-HEADER-VALUE']) && ($attr['TOC-EVEN-HEADER-VALUE'] == '-1' || strtoupper($attr['TOC-EVEN-HEADER-VALUE']) == 'OFF')) {
                $this->m_TOC[$toc_id]['TOC_even_header_value'] = -1;
            }
            if (isset($attr['TOC-ODD-FOOTER-VALUE']) && ($attr['TOC-ODD-FOOTER-VALUE'] == '1' || strtoupper($attr['TOC-ODD-FOOTER-VALUE']) == 'ON')) {
                $this->m_TOC[$toc_id]['TOC_odd_footer_value'] = 1;
            } elseif (isset($attr['TOC-ODD-FOOTER-VALUE']) && ($attr['TOC-ODD-FOOTER-VALUE'] == '-1' || strtoupper($attr['TOC-ODD-FOOTER-VALUE']) == 'OFF')) {
                $this->m_TOC[$toc_id]['TOC_odd_footer_value'] = -1;
            }
            if (isset($attr['TOC-EVEN-FOOTER-VALUE']) && ($attr['TOC-EVEN-FOOTER-VALUE'] == '1' || strtoupper($attr['TOC-EVEN-FOOTER-VALUE']) == 'ON')) {
                $this->m_TOC[$toc_id]['TOC_even_footer_value'] = 1;
            } elseif (isset($attr['TOC-EVEN-FOOTER-VALUE']) && ($attr['TOC-EVEN-FOOTER-VALUE'] == '-1' || strtoupper($attr['TOC-EVEN-FOOTER-VALUE']) == 'OFF')) {
                $this->m_TOC[$toc_id]['TOC_even_footer_value'] = -1;
            }
            if (isset($attr['TOC-RESETPAGENUM']) && $attr['TOC-RESETPAGENUM']) {
                $this->m_TOC[$toc_id]['TOC_resetpagenum'] = $attr['TOC-RESETPAGENUM'];
            } else {
                $this->m_TOC[$toc_id]['TOC_resetpagenum'] = '';
            }
            // mPDF 6
            if (isset($attr['TOC-PAGENUMSTYLE']) && $attr['TOC-PAGENUMSTYLE']) {
                $this->m_TOC[$toc_id]['TOC_pagenumstyle'] = $attr['TOC-PAGENUMSTYLE'];
            } else {
                $this->m_TOC[$toc_id]['TOC_pagenumstyle'] = '';
            }
            // mPDF 6
            if (isset($attr['TOC-SUPPRESS']) && ($attr['TOC-SUPPRESS'] || $attr['TOC-SUPPRESS'] === '0')) {
                $this->m_TOC[$toc_id]['TOC_suppress'] = $attr['TOC-SUPPRESS'];
            } else {
                $this->m_TOC[$toc_id]['TOC_suppress'] = '';
            }
            // mPDF 6
            if (isset($attr['TOC-PAGE-SELECTOR']) && $attr['TOC-PAGE-SELECTOR']) {
                $this->m_TOC[$toc_id]['TOC_page_selector'] = $attr['TOC-PAGE-SELECTOR'];
            } else {
                $this->m_TOC[$toc_id]['TOC_page_selector'] = '';
            }
            if (isset($attr['TOC-SHEET-SIZE']) && $attr['TOC-SHEET-SIZE']) {
                $this->m_TOC[$toc_id]['TOCsheetsize'] = $attr['TOC-SHEET-SIZE'];
            } else {
                $this->m_TOC[$toc_id]['TOCsheetsize'] = '';
            }
            if (isset($attr['TOC-PREHTML']) && $attr['TOC-PREHTML']) {
                $this->m_TOC[$toc_id]['TOCpreHTML'] = htmlspecialchars_decode($attr['TOC-PREHTML'], ENT_QUOTES);
            }
            if (isset($attr['TOC-POSTHTML']) && $attr['TOC-POSTHTML']) {
                $this->m_TOC[$toc_id]['TOCpostHTML'] = htmlspecialchars_decode($attr['TOC-POSTHTML'], ENT_QUOTES);
            }
            if (isset($attr['TOC-BOOKMARKTEXT']) && $attr['TOC-BOOKMARKTEXT']) {
                $this->m_TOC[$toc_id]['TOCbookmarkText'] = htmlspecialchars_decode($attr['TOC-BOOKMARKTEXT'], ENT_QUOTES);
            }
            // *BOOKMARKS*
        } else {
            if (isset($attr['OUTDENT']) && $attr['OUTDENT']) {
                $this->to_coutdent = $attr['OUTDENT'];
            } else {
                $this->to_coutdent = '';
            }
            // mPDF 5.6.19
            if (isset($attr['TOC-ORIENTATION']) && $attr['TOC-ORIENTATION']) {
                $this->to_corientation = $attr['TOC-ORIENTATION'];
            } else {
                $this->to_corientation = '';
            }
            if (isset($attr['PAGING']) && (strtoupper($attr['PAGING']) == 'OFF' || $attr['PAGING'] === '0')) {
                $this->to_cuse_paging = false;
            } else {
                $this->to_cuse_paging = true;
            }
            if (isset($attr['LINKS']) && (strtoupper($attr['LINKS']) == 'ON' || $attr['LINKS'] == 1)) {
                $this->to_cuse_linking = true;
            } else {
                $this->to_cuse_linking = false;
            }
            $this->TOC_margin_left = $this->TOC_margin_right = $this->TOC_margin_top = $this->TOC_margin_bottom = $this->TOC_margin_header = $this->TOC_margin_footer = '';
            if (isset($attr['TOC-MARGIN-RIGHT'])) {
                $this->TOC_margin_right = $this->size_converter->convert($attr['TOC-MARGIN-RIGHT'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-LEFT'])) {
                $this->TOC_margin_left = $this->size_converter->convert($attr['TOC-MARGIN-LEFT'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-TOP'])) {
                $this->TOC_margin_top = $this->size_converter->convert($attr['TOC-MARGIN-TOP'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-BOTTOM'])) {
                $this->TOC_margin_bottom = $this->size_converter->convert($attr['TOC-MARGIN-BOTTOM'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-HEADER'])) {
                $this->TOC_margin_header = $this->size_converter->convert($attr['TOC-MARGIN-HEADER'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            if (isset($attr['TOC-MARGIN-FOOTER'])) {
                $this->TOC_margin_footer = $this->size_converter->convert($attr['TOC-MARGIN-FOOTER'], $this->mpdf->w, $this->mpdf->font_size, false);
            }
            $this->TOC_odd_header_name = $this->TOC_even_header_name = $this->TOC_odd_footer_name = $this->TOC_even_footer_name = '';
            if (isset($attr['TOC-ODD-HEADER-NAME']) && $attr['TOC-ODD-HEADER-NAME']) {
                $this->TOC_odd_header_name = $attr['TOC-ODD-HEADER-NAME'];
            }
            if (isset($attr['TOC-EVEN-HEADER-NAME']) && $attr['TOC-EVEN-HEADER-NAME']) {
                $this->TOC_even_header_name = $attr['TOC-EVEN-HEADER-NAME'];
            }
            if (isset($attr['TOC-ODD-FOOTER-NAME']) && $attr['TOC-ODD-FOOTER-NAME']) {
                $this->TOC_odd_footer_name = $attr['TOC-ODD-FOOTER-NAME'];
            }
            if (isset($attr['TOC-EVEN-FOOTER-NAME']) && $attr['TOC-EVEN-FOOTER-NAME']) {
                $this->TOC_even_footer_name = $attr['TOC-EVEN-FOOTER-NAME'];
            }
            $this->TOC_odd_header_value = $this->TOC_even_header_value = $this->TOC_odd_footer_value = $this->TOC_even_footer_value = 0;
            if (isset($attr['TOC-ODD-HEADER-VALUE']) && ($attr['TOC-ODD-HEADER-VALUE'] == '1' || strtoupper($attr['TOC-ODD-HEADER-VALUE']) == 'ON')) {
                $this->TOC_odd_header_value = 1;
            } elseif (isset($attr['TOC-ODD-HEADER-VALUE']) && ($attr['TOC-ODD-HEADER-VALUE'] == '-1' || strtoupper($attr['TOC-ODD-HEADER-VALUE']) == 'OFF')) {
                $this->TOC_odd_header_value = -1;
            }
            if (isset($attr['TOC-EVEN-HEADER-VALUE']) && ($attr['TOC-EVEN-HEADER-VALUE'] == '1' || strtoupper($attr['TOC-EVEN-HEADER-VALUE']) == 'ON')) {
                $this->TOC_even_header_value = 1;
            } elseif (isset($attr['TOC-EVEN-HEADER-VALUE']) && ($attr['TOC-EVEN-HEADER-VALUE'] == '-1' || strtoupper($attr['TOC-EVEN-HEADER-VALUE']) == 'OFF')) {
                $this->TOC_even_header_value = -1;
            }
            if (isset($attr['TOC-ODD-FOOTER-VALUE']) && ($attr['TOC-ODD-FOOTER-VALUE'] == '1' || strtoupper($attr['TOC-ODD-FOOTER-VALUE']) == 'ON')) {
                $this->TOC_odd_footer_value = 1;
            } elseif (isset($attr['TOC-ODD-FOOTER-VALUE']) && ($attr['TOC-ODD-FOOTER-VALUE'] == '-1' || strtoupper($attr['TOC-ODD-FOOTER-VALUE']) == 'OFF')) {
                $this->TOC_odd_footer_value = -1;
            }
            if (isset($attr['TOC-EVEN-FOOTER-VALUE']) && ($attr['TOC-EVEN-FOOTER-VALUE'] == '1' || strtoupper($attr['TOC-EVEN-FOOTER-VALUE']) == 'ON')) {
                $this->TOC_even_footer_value = 1;
            } elseif (isset($attr['TOC-EVEN-FOOTER-VALUE']) && ($attr['TOC-EVEN-FOOTER-VALUE'] == '-1' || strtoupper($attr['TOC-EVEN-FOOTER-VALUE']) == 'OFF')) {
                $this->TOC_even_footer_value = -1;
            }
            if (isset($attr['TOC-PAGE-SELECTOR']) && $attr['TOC-PAGE-SELECTOR']) {
                $this->TOC_page_selector = $attr['TOC-PAGE-SELECTOR'];
            } else {
                $this->TOC_page_selector = '';
            }
            if (isset($attr['TOC-RESETPAGENUM']) && $attr['TOC-RESETPAGENUM']) {
                $this->TOC_resetpagenum = $attr['TOC-RESETPAGENUM'];
            } else {
                $this->TOC_resetpagenum = '';
            }
            // mPDF 6
            if (isset($attr['TOC-PAGENUMSTYLE']) && $attr['TOC-PAGENUMSTYLE']) {
                $this->TOC_pagenumstyle = $attr['TOC-PAGENUMSTYLE'];
            } else {
                $this->TOC_pagenumstyle = '';
            }
            // mPDF 6
            if (isset($attr['TOC-SUPPRESS']) && ($attr['TOC-SUPPRESS'] || $attr['TOC-SUPPRESS'] === '0')) {
                $this->TOC_suppress = $attr['TOC-SUPPRESS'];
            } else {
                $this->TOC_suppress = '';
            }
            // mPDF 6
            if (isset($attr['TOC-SHEET-SIZE']) && $attr['TOC-SHEET-SIZE']) {
                $this->to_csheetsize = $attr['TOC-SHEET-SIZE'];
            } else {
                $this->to_csheetsize = '';
            }
            if (isset($attr['TOC-PREHTML']) && $attr['TOC-PREHTML']) {
                $this->to_cpre_html = htmlspecialchars_decode($attr['TOC-PREHTML'], ENT_QUOTES);
            }
            if (isset($attr['TOC-POSTHTML']) && $attr['TOC-POSTHTML']) {
                $this->to_cpost_html = htmlspecialchars_decode($attr['TOC-POSTHTML'], ENT_QUOTES);
            }
            if (isset($attr['TOC-BOOKMARKTEXT']) && $attr['TOC-BOOKMARKTEXT']) {
                $this->to_cbookmark_text = htmlspecialchars_decode($attr['TOC-BOOKMARKTEXT'], ENT_QUOTES);
            }
        }
        if ($this->mpdf->y == $this->mpdf->t_margin && (!$this->mpdf->mirror_margins || $this->mpdf->mirror_margins && $this->mpdf->page % 2 == 1)) {
            if ($toc_id) {
                $this->m_TOC[$toc_id]['TOCmark'] = $this->mpdf->page;
            } else {
                $this->to_cmark = $this->mpdf->page;
            }
            // Don't add a page
            if ($this->mpdf->page == 1 && count($this->mpdf->page_num_substitutions) == 0) {
                $resetpagenum = '';
                $pagenumstyle = '';
                $suppress = '';
                if (isset($attr['RESETPAGENUM'])) {
                    $resetpagenum = $attr['RESETPAGENUM'];
                }
                if (isset($attr['PAGENUMSTYLE'])) {
                    $pagenumstyle = $attr['PAGENUMSTYLE'];
                }
                if (isset($attr['SUPPRESS'])) {
                    $suppress = $attr['SUPPRESS'];
                }
                if (!$suppress) {
                    $suppress = 'off';
                }
                $this->mpdf->page_num_substitutions[] = ['from' => 1, 'reset' => $resetpagenum, 'type' => $pagenumstyle, 'suppress' => $suppress];
            }
            return [true, $toc_id];
        }
        // No break - continues as PAGEBREAK...
        return [false, $toc_id];
    }
}