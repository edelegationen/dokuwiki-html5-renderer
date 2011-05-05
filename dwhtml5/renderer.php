<?php
/**
 * A HTML5 compatible renderer for Dokuwiki.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author E-delegationen info@edelegationen.se>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

// we inherit from the XHTML renderer instead directly of the base renderer
require_once DOKU_INC.'inc/parser/xhtml.php';

class renderer_plugin_dwhtml5 extends Doku_Renderer_xhtml {

    function getInfo(){
        return confToHash(dirname(__FILE__).'/README');
    }

    function getFormat(){
        return 'xhtml';
    }

    function canRender($format) {
        return ($format=='xhtml');
    }


    // Removed title attribute which previously was set to URL (?)
    function locallink($hash, $name = NULL){
        global $ID;
        $name  = $this->_getLinkTitle($name, $hash, $isImage);
        $hash  = $this->_headerToLink($hash);
        $this->doc .= '<a href="#'.$hash.'" class="wikilink1">';
        $this->doc .= $name;
        $this->doc .= '</a>';
    }

    /**
     * Removed title attribute which previously was set to URL (?)
    */
    function interwikilink($match, $name = NULL, $wikiName, $wikiUri) {
        global $conf;

        $link = array();
        $link['target'] = $conf['target']['interwiki'];
        $link['pre']    = '';
        $link['suf']    = '';
        $link['more']   = '';
        $link['name']   = $this->_getLinkTitle($name, $wikiUri, $isImage);

        //get interwiki URL
        $url = $this->_resolveInterWiki($wikiName,$wikiUri);

        if ( !$isImage ) {
            $class = preg_replace('/[^_\-a-z0-9]+/i','_',$wikiName);
            $link['class'] = "interwiki iw_$class";
        } else {
            $link['class'] = 'media';
        }

        //do we stay at the same server? Use local target
        if( strpos($url,DOKU_URL) === 0 ){
            $link['target'] = $conf['target']['wiki'];
        }

        $link['url'] = $url;

        //output formatted
        $this->doc .= $this->_formatLink($link);
    }





    function internallink($id, $name = NULL, $search=NULL,$returnonly=false,$linktype='content') {
        global $conf;
        global $ID;

        $params = '';
        $parts = explode('?', $id, 2);
        if (count($parts) === 2) {
            $id = $parts[0];
            $params = $parts[1];
        }

        // default name is based on $id as given
        $default = $this->_simpleTitle($id);

        // now first resolve and clean up the $id
        resolve_pageid(getNS($ID),$id,$exists);
        $name = $this->_getLinkTitle($name, $default, $isImage, $id, $linktype);
        if ( !$isImage ) {
            if ( $exists ) {
                $class='wikilink1';
            } else {
                $class='wikilink2';
                $link['rel']='nofollow';
            }
        } else {
            $class='media';
        }

        //keep hash anchor
        list($id,$hash) = explode('#',$id,2);
        if(!empty($hash)) $hash = $this->_headerToLink($hash);

        //prepare for formating
        $link['target'] = $conf['target']['wiki'];
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        // highlight link to current page
        if ($id == $ID) {
            $link['pre']    = '<span class="curid">';
            $link['suf']    = '</span>';
        }
        $link['more']   = '';
        $link['class']  = $class;
        $link['url']    = wl($id, $params);
        $link['name']   = $name;
        //add search string
        if($search){
            ($conf['userewrite']) ? $link['url'].='?' : $link['url'].='&amp;';
            if(is_array($search)){
                $search = array_map('rawurlencode',$search);
                $link['url'] .= 's[]='.join('&amp;s[]=',$search);
            }else{
                $link['url'] .= 's='.rawurlencode($search);
            }
        }

        //keep hash
        if($hash) $link['url'].='#'.$hash;

        //output formatted
        if($returnonly){
            return $this->_formatLink($link);
        }else{
            $this->doc .= $this->_formatLink($link);
        }
    }






    function header($text, $level, $pos) {
        global $conf;

        if(!$text) return; //skip empty headlines

        $hid = $this->_headerToLink($text,true);

        //only add items within configured levels
        $this->toc_additem($hid, $text, $level);

        // adjust $node to reflect hierarchy of levels
        $this->node[$level-1]++;
        if ($level < $this->lastlevel) {
            for ($i = 0; $i < $this->lastlevel-$level; $i++) {
                $this->node[$this->lastlevel-$i-1] = 0;
            }
        }
        $this->lastlevel = $level;

        if ($level <= $conf['maxseclevel'] &&
            count($this->sectionedits) > 0 &&
            $this->sectionedits[count($this->sectionedits) - 1][2] === 'section') {
            $this->finishSectionEdit($pos - 1);
        }

        // write the header
        $this->doc .= DOKU_LF.'<h'.$level.' id="'.$hid.'"';
        if ($level <= $conf['maxseclevel']) {
            $this->doc .= ' class="' . $this->startSectionEdit($pos, 'section', $text) . '"';
        }
        $this->doc .= '>'.$this->_xmlEntities($text);
        $this->doc .= "</h$level>".DOKU_LF;
    }



    function _media ($src, $title=NULL, $align=NULL, $width=NULL,
                      $height=NULL, $cache=NULL, $render = true) {

        $ret = '';

        list($ext,$mime,$dl) = mimetype($src);
        if(substr($mime,0,5) == 'image'){
            // first get the $title
            if (!is_null($title)) {
                $title  = $this->_xmlEntities($title);
            }elseif($ext == 'jpg' || $ext == 'jpeg'){
                //try to use the caption from IPTC/EXIF
                require_once(DOKU_INC.'inc/JpegMeta.php');
                $jpeg =new JpegMeta(mediaFN($src));
                if($jpeg !== false) $cap = $jpeg->getTitle();
                if($cap){
                    $title = $this->_xmlEntities($cap);
                }
            }
            if (!$render) {
                // if the picture is not supposed to be rendered
                // return the title of the picture
                if (!$title) {
                    // just show the sourcename
                    $title = $this->_xmlEntities(basename(noNS($src)));
                }
                return $title;
            }
            //add image tag
            $ret .= '<img src="'.ml($src,array('w'=>$width,'h'=>$height,'cache'=>$cache)).'"';
            $ret .= ' class="media'.$align.'"';

            // make left/right alignment for no-CSS view work (feeds)
            //if($align == 'right') $ret .= ' align="right"';
            //if($align == 'left')  $ret .= ' align="left"';

            if ($title) {
                $ret .= ' title="' . $title . '"';
                $ret .= ' alt="'   . $title .'"';
            }else{
                $ret .= ' alt=""';
            }

            if ( !is_null($width) )
                $ret .= ' width="'.$this->_xmlEntities($width).'"';

            if ( !is_null($height) )
                $ret .= ' height="'.$this->_xmlEntities($height).'"';

            $ret .= ' />';

        }elseif($mime == 'application/x-shockwave-flash'){
            if (!$render) {
                // if the flash is not supposed to be rendered
                // return the title of the flash
                if (!$title) {
                    // just show the sourcename
                    $title = basename(noNS($src));
                }
                return $this->_xmlEntities($title);
            }

            $att = array();
            $att['class'] = "media$align";
            if($align == 'right') $att['align'] = 'right';
            if($align == 'left')  $att['align'] = 'left';
            $ret .= html_flashobject(ml($src,array('cache'=>$cache),true,'&'),$width,$height,
                                     array('quality' => 'high'),
                                     null,
                                     $att,
                                     $this->_xmlEntities($title));
        }elseif($title){
            // well at least we have a title to display
            $ret .= $this->_xmlEntities($title);
        }else{
            // just show the sourcename
            $ret .= $this->_xmlEntities(basename(noNS($src)));
        }

        return $ret;
    }




   function footnote_close() {

        // recover footnote into the stack and restore old content
        $footnote = $this->doc;
        $this->doc = $this->store;
        $this->store = '';

        // check to see if this footnote has been seen before
        $i = array_search($footnote, $this->footnotes);

        if ($i === false) {
            // its a new footnote, add it to the $footnotes array
            $id = count($this->footnotes)+1;
            $this->footnotes[count($this->footnotes)] = $footnote;
        } else {
            // seen this one before, translate the index to an id and save a placeholder
            $i++;
            $id = count($this->footnotes)+1;
            $this->footnotes[count($this->footnotes)] = "@@FNT".($i);
        }

        // output the footnote reference and link
        $this->doc .= '<sup><a href="#fn__'.$id.'" id="fnt__'.$id.'" class="fn_top">'.$id.')</a></sup>';
    }


    // Removed title attribute which previously was set to url (?)
    function externallink($url, $name = NULL) {
        global $conf;

        $name = $this->_getLinkTitle($name, $url, $isImage);

        if ( !$isImage ) {
            $class='urlextern';
        } else {
            $class='media';
        }

        //prepare for formating
        $link['target'] = $conf['target']['extern'];
        $link['style']  = '';
        $link['pre']    = '';
        $link['suf']    = '';
        $link['more']   = '';
        $link['class']  = $class;
        $link['url']    = $url;

        $link['name']   = $name;

        if($conf['relnofollow']) $link['more'] .= ' rel="nofollow"';

        //output formatted
        $this->doc .= $this->_formatLink($link);
    }




    /**
     * Use GeSHi to highlight language syntax in code and file blocks
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function _highlight($type, $text, $language=null, $filename=null) {
        global $conf;
        global $ID;
        global $lang;

        if($filename){
            // add icon
            list($ext) = mimetype($filename,false);
            $class = preg_replace('/[^_\-a-z0-9]+/i','_',$ext);
            $class = 'mediafile mf_'.$class;

            $this->doc .= '<dl class="'.$type.'">'.DOKU_LF;
            $this->doc .= '<dt><a href="'.exportlink($ID,'code',array('codeblock'=>$this->_codeblock)).'" title="'.$lang['download'].'" class="'.$class.'">';
            $this->doc .= hsc($filename);
            $this->doc .= '</a></dt>'.DOKU_LF.'<dd>';
        }

        if ($text{0} == "\n") {
            $text = substr($text, 1);
        }
        if (substr($text, -1) == "\n") {
            $text = substr($text, 0, -1);
        }

        if ( is_null($language) ) {
            $this->doc .= '<pre class="'.$type.'">'.$this->_xmlEntities($text).'</pre>'.DOKU_LF;
        } else {
            $class = 'code'; //we always need the code class to make the syntax highlighting apply
            if($type != 'code') $class .= ' '.$type;

            $this->doc .= "<code class=\"$class $language\">".p_xhtml_cached_geshi($text, $language, '').'</code>'.DOKU_LF;
        }

        if($filename){
            $this->doc .= '</dd></dl>'.DOKU_LF;
        }

        $this->_codeblock++;
    }





    function acronym($acronym) {

        if ( array_key_exists($acronym, $this->acronyms) ) {

            $title = $this->_xmlEntities($this->acronyms[$acronym]);

            $this->doc .= '<abbr title="'.$title
                .'">'.$this->_xmlEntities($acronym).'</abbr>';

        } else {
            $this->doc .= $this->_xmlEntities($acronym);
        }
    }


    function document_end() {
        // Finish open section edits.
        while (count($this->sectionedits) > 0) {
            if ($this->sectionedits[count($this->sectionedits) - 1][1] <= 1) {
                // If there is only one section, do not write a section edit
                // marker.
                array_pop($this->sectionedits);
            } else {
                $this->finishSectionEdit();
            }
        }

        if ( count ($this->footnotes) > 0 ) {
            $this->doc .= '<div class="footnotes">'.DOKU_LF;

            $id = 0;
            foreach ( $this->footnotes as $footnote ) {
                $id++;   // the number of the current footnote

                // check its not a placeholder that indicates actual footnote text is elsewhere
                if (substr($footnote, 0, 5) != "@@FNT") {

                    // open the footnote and set the anchor and backlink
                    $this->doc .= '<div class="fn">';
                    $this->doc .= '<sup><a href="#fnt__'.$id.'" id="fn__'.$id.'" class="fn_bot">';
                    $this->doc .= $id.')</a></sup> '.DOKU_LF;

                    // get any other footnotes that use the same markup
                    $alt = array_keys($this->footnotes, "@@FNT$id");

                    if (count($alt)) {
                      foreach ($alt as $ref) {
                        // set anchor and backlink for the other footnotes
                        $this->doc .= ', <sup><a href="#fnt__'.($ref+1).'" id="fn__'.($ref+1).'" class="fn_bot">';
                        $this->doc .= ($ref+1).')</a></sup> '.DOKU_LF;
                      }
                    }

                    // add footnote markup and close this footnote
                    $this->doc .= $footnote;
                    $this->doc .= '</div>' . DOKU_LF;
                }
            }
            $this->doc .= '</div>'.DOKU_LF;
        }

        // Prepare the TOC
        global $conf;
        if($this->info['toc'] && is_array($this->toc) && $conf['tocminheads'] && count($this->toc) >= $conf['tocminheads']){
            global $TOC;
            $TOC = $this->toc;
        }

        // make sure there are no empty paragraphs
        $this->doc = preg_replace('#<p>\s*</p>#','',$this->doc);
    }
}

