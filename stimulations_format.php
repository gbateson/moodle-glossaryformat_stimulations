<?php

defined('MOODLE_INTERNAL') || die();

function glossary_show_entry_stimulations($course, $cm, $glossary, $entry, $mode='', $hook='', $printicons=1, $ratings=NULL, $aliases=true) {
    // $aliases    : true=show the aliases popup, false=hide the aliases popup
    // $ratings    : NULL=don't show rating
    // $printicons : true=show editing icons, false=hide editing icons

    // http://localhost/19/mod/glossary/showentry.php?courseid=2&concept=Where+are+you+from%3F

    global $CFG, $DB, $PAGE;
    $return = true;

    static $number = 0;
    static $hideheader = null;
    static $hidefooter = null;

    if (is_null($hideheader)) {
        $hideheader = optional_param('hideheader', 0, PARAM_INT);
        $hidefooter = optional_param('hidefooter', 1, PARAM_INT);
    }

    // switches to hide css elements
    $hide = (object)array(
        // css
        'header'       => $hideheader,
        'navbar'       => $hideheader,
        'intro'        => true,
        'searchbox'    => false,
        'singlebutton' => true,
        'tabtree'      => true,
        'hr'           => true,
        'explain'      => true,
        'categories'   => true,
        'footer'       => $hidefooter,
    );

    // switches to show table columns
    $show = (object)array(
        'number'       => ($number > 0),
        'audio'        => true,
        'concept'      => true,
        'definition'   => true,
        'icons'        => true,
    );

    static $search_category_name = 'すべて';
    static $default_category_name = '/(単語|(語彙・語句))$/';
    static $default_url = '';
    static $bgcolor = '';
    static $disable_copy = true;

    static $concept = null;

    if (is_string($concept) && $concept) {
        return $return; // only print one definition per concept
    }

    // stuff we do first time only ...
    if (is_null($concept)) {

        // get concept, if any
        $concept = optional_param('concept', '', PARAM_CLEAN);

        if ($mode=='search') {
            $select = 'glossaryid = ? AND '.$DB->sql_like('name', '?');
            $params = array($glossary->id, '%'.$search_category_name.'%');
            $category = $DB->get_record_select('glossary_categories', $select, $params);
            glossary_print_categories_menu($cm, $glossary, $hook, $category);
        } else {
            $category = false;
        }

        // get specified category, if any
        if ($mode=='cat' && $hook) {
            $category = $DB->get_record('glossary_categories', array('id' => $hook));
        }

        // if no category is specified, get default category
        if (empty($category)) {
            if ($records = $DB->get_records('glossary_categories', array('glossaryid' => $glossary->id), 'name')) {
                foreach ($records as $record) {
                    if (preg_match($default_category_name, $record->name)) {
                        $params = array('id' => $cm->id, 'mode' => 'cat', 'hook' => $record->id);
                        $default_url = new moodle_url('/mod/glossary/view.php', $params);
                    }
                }
                unset($records, $record);
            }
        }

        if ($disable_copy) {
            $disable_copy = ' unselectable="true"'.
                            ' oncontextmenu="return false;"'.
                            ' oncopy="return false;"'.
                            ' oncut="return false;"'.
                            ' ondrag="return false;"'.
                            ' onselectstart="return false;"';
        }

        // set columns widths and hide/show sections
        if ($concept) {

            $width[0] = 0;
            $width[1] = 110;
            $width[2] = 230;
            $width[3] = 230;
            $width[4] = 60;

            $hide->header = true;
            $hide->navbar = true;
            $hide->footer = true;
            $show->number = false;

        } else {

            $width[0] = 20;
            $width[1] = 110;
            $width[2] = 460;
            $width[3] = 0;
            $width[4] = 60;

            switch (true) {
                case $mode=='search':
                case empty($category->name):
                case preg_match('/すべて/', $category->name)    : $width[2] = 400; $hide->searchbox = false; break;
                case preg_match('/語彙|単語/', $category->name) : $width[2] = 200; break;
                case preg_match('/質問|答え/', $category->name) : $width[2] = 400; break;
                case preg_match('/会話|文章/', $category->name) : $width[2] = 460; break;
            }
        }

        if (isset($category->name) && preg_match('/語彙|単語|語句/', $category->name)) {
            $show->number = true;
        }

        // convert widths to strings
        foreach ($width as $i => $w) {
            if ($w==0) {
                $width[$i] = 'auto';
            } else {
                $width[$i] = $w.'px';
            }
        }

        $css = array();

        // hide the unwanted stuff
        if ($hide->header) {
            $css[] = 'div#header';
        }
        if ($hide->navbar) {
            $css[] = 'div.navbar';
        }
        if ($hide->intro) {
            $css[] = 'div#intro';
        }
        if ($hide->searchbox) {
            $css[] = 'td.glossarysearchbox';
        }
        if ($hide->singlebutton) {
            $css[] = 'div.singlebutton';
            // see below for css to remove line breaks
        }
        if ($hide->tabtree) {
            $css[] = 'div.glossarydisplay div.tabtree';
            //$css[] = 'div.glossarydisplay div.clearer';
        }
        if ($hide->hr) {
            $css[] = 'div.glossarydisplay div.entrybox hr';
        }
        if ($hide->explain) {
            $css[] = 'div.glossarydisplay div.entrybox div.glossaryexplain';
        }
        if ($hide->categories) {
            // hide "All categories" and "Not categorized" options
            // (i.e. the first and second options on the category menu)
            // Note: you can't hide options with CSS in IE (groan)
            // so we remove the items later with javascript (see below)
            //$css[] = 'div.glossarydisplay div.entrybox select#catmenu_jump option:first-child';
            //$css[] = 'div.glossarydisplay div.entrybox select#catmenu_jump option:first-child + option';
        }
        if ($hide->footer) {
            // basically we hide everything in the footer
            // but we will show the div.logininfo later
            $css[] = 'div#footer hr';
            $css[] = 'div#footer div';
            $css[] = 'div#footer p';
        }

        // convert $css to string
        if ($css = implode(', ', $css)) {
            $css .= ' { display: none; }';
        }

        if ($hide->singlebutton) {
            // remove line breaks before and after div.singlebutton
            // Note: put these separately because IE6 can't handle them
            $css .= 'form + br, div.singlebutton + br { display: none; }';
        }

        // in the paging div, replace the line breaks with a space
        $css .= 'div.paging p br { display: none; }';
        $css .= 'div.paging p br + a:before { content: " "; }';
        $css .= 'div.paging p br + b:before { content: " "; }';

        // remove line break following bottom paging div
        $css .= 'div.paging + br { display: none; }';

        // add top border to main entry box, if tabtree is hidden
        if ($hide->tabtree) {
            $css .= 'div.entrybox { border-top-width: 1px; }';
        }

        // reduce top margin on footer and show logout link
        if ($hide->footer) {
            $css .= 'div#footer { margin-top: 12px }';
            $css .= 'div#footer div.logininfo { display: block; }';
        }

        // css for columns in glossarypost table
        $css .= 'table.glossarypost td.entry { text-align: left; vertical-align: top; padding: 6px; }';

        if ($show->number) {
            $css .= 'table.glossarypost td.number { width: '.$width[0].'; text-align: center; }';
        }
        if ($show->audio) {
            $css .= 'table.glossarypost td.audio { width: '.$width[1].'; padding: 0px; }';
            $css .= 'table.glossarypost td.audio a { display: none; } ';
            $css .= 'table.glossarypost td.audio audio { max-width: initial; } ';
            $css .= 'table.glossarypost td.audio .mediaplugin { width: 200px; margin-top: 0px; margin-bottom: 0px; }';
        }
        if ($show->concept) {
            $css .= 'table.glossarypost td.concept { width: '.$width[2].'; } ';
            $css .= 'table.glossarypost td.concept h3, ';
            $css .= 'table.glossarypost td.concept h4 { margin-top: 0px; margin-bottom: 0px; }';
        }
        if ($show->definition) {
            $css .= 'table.glossarypost td.definition { width: '.$width[3].'; }';
        }
        if ($show->icons) {
            $css .= 'table.glossarypost td.icons { width: '.$width[4].'; text-align: center; }';
            //$css .= 'table.glossarypost td.icons table {  }';
        }

        // javascript to add extra styles after <head> tag has loaded
        echo '<script type="text/javascript">'."\n";
        echo "//<![CDATA[\n";
        echo "    var txt = '$css';\n";
        if ($disable_copy) {
            echo "    var css_prefix = '';\n";
            echo "    var div = document.createElement('div');\n";
            echo "    var css_prefixes = new Array('webkit', 'khtml', 'moz', 'ms', 'o', '');\n";
            echo "    var i_max = css_prefixes.length;\n";
            echo "    for (var i=0; i<i_max; i++) {\n";
            echo "        css_prefix = css_prefixes[i];\n";
            echo "        if (typeof(div['style'][css_prefix + 'UserSelect']) != 'undefined') {\n";
            echo "            if (css_prefix) {\n";
            echo "                css_prefix = ('-' + css_prefix + '-');\n";
            echo "            }\n";
            echo "            txt += 'table.glossarypost td.concept { ' + css_prefix + 'user-select : none; }';\n";
            echo "            txt += 'table.glossarypost td.definition { ' + css_prefix + 'user-select : none; }';\n";
            echo "            break;\n";
            echo "        }\n";
            echo "    }\n";
            echo "    div = null;\n";
            echo "    css_prefix = null;\n";
            echo "    css_prefixes = null;\n";
        }
        echo "    var obj = document.createElement('style');\n";
        echo "    obj.setAttribute('type', 'text/css');\n";
        echo "    if (obj.styleSheet) {\n";
        echo "        obj.styleSheet.cssText = txt;\n";
        echo "    } else {\n";
        echo "        obj.appendChild(document.createTextNode(txt));\n";
        echo "    }\n";
        echo "    document.getElementsByTagName('head')[0].appendChild(obj);\n";
        echo "\n";
        echo "    var m = navigator.userAgent.match(new RegExp('MSIE (\\d+)'));\n";
        echo "    if (m && m[1]<=7) {\n";
        echo "        // IE7 and earlier\n";
        echo "        var classAttribute = 'className';\n";
        echo "    } else {\n";
        echo "        var classAttribute = 'class';\n";
        echo "    }\n";
        echo "\n";
        echo "    var obj = document.getElementsByTagName('div');\n";
        echo "    var i_max = obj.length;\n";
        echo "    for (var i=0; i<i_max; i++) {\n";
        echo "        if (obj[i].getAttribute(classAttribute)=='entrybox') {\n";
        echo "            var ii_max = obj[i].childNodes.length - 1;\n";
        echo "            for (var ii=ii_max; ii>=0; ii--) {\n";
        echo "                var childNode = obj[i].childNodes[ii];\n";
        echo "                switch (childNode.nodeType) {\n";
        echo "                    case 1: var removeChildNode = (childNode.tagName=='A' || childNode.tagName=='BR'); break;\n";
        echo "                    case 3: var removeChildNode = true; break;\n"; // text node e.g. "|"
        echo "                    default: var removeChildNode = false; break;\n";
        echo "                }\n";
        echo "                if (removeChildNode) {\n";
        echo "                    obj[i].removeChild(childNode);\n";
        echo "                }\n";
        echo "            }\n";
        echo "            break;\n";
        echo "        }\n";
        echo "    }\n";
        if ($hide->categories) {
            // IE cannot hide items with CSS, so we forcibly remove them with JavaScript
            // Note: some browsers, e.g. FF3, do not have obj.options.remove() method
            echo "\n";
            echo "    var obj = document.getElementById('catmenu_jump');\n";
            echo "    if (obj) {\n";
            echo "        var i_max = obj.options.length;\n";
            echo "        for (var i=i_max-1; i>=0; i--) {\n";
            echo "            if (obj.options[i].value.match(new RegExp('hook=(-1|0)'))) {\n";
            echo "                if (obj.options.remove) {\n";
            echo "                    obj.options.remove(i);\n";
            echo "                } else {\n";
            echo "                    obj.options[i] = null;\n";
            echo "                }\n";
            echo "            }\n";
            echo "        }\n";
            echo "    }\n";
        }
        echo "    obj = null;\n";
        echo "//]]>\n";
        echo '</script>'."\n";

        // redirect to default category, if necessary
        if ($concept=='' && $default_url) {
            $CFG->debug = false;
            redirect($default_url, ' ', 0);
        }
    }

    if ($entry) {

        // remove the link to the full glossary
        if (isset($entry->footer)) {
            unset($entry->footer);
        }

        if (empty($entry->footer)) {
            $footer = '';
        } else {
            $footer = $entry->footer;
            unset($entry->footer);
        }

        if ($footer) {
            if ($default_url) {
                $search = '/'.preg_quote($CFG->wwwroot.'/mod/glossary/view.php?', '/').'g=[0-9]+/';
                $replace = $default_url.'$1'.'&amp;hideheader=1';
                $footer = preg_replace($search, $replace, $footer);
            }

            echo $footer;
        }

        if (empty($bgcolor)) {
            $style = '';
            $bgcolor = '#eeeeee';
        } else {
            $style = ' style="background-color: '.$bgcolor.'"';
            $bgcolor = '';
        }

        echo '<table class="glossarypost stimulations"'.$style.'>';

        // extract audio link, if any
        if (preg_match('/^(<a [^>]*><\/a>)\s*(.*)$/', $entry->definition, $matches)) {
            $entry->audio = $matches[1];
            $entry->definition = $matches[2];
        } else {
            $entry->audio = '';
        }

        echo '<tr>';

        if ($show->number) {
            echo '<td class="entry number">';
            echo ++$number.'.';
            echo '</td>';
        }

        if ($show->audio) {
            echo '<td class="entry audio">';
            $entry->audio = preg_replace('/(?<=href=")\\.[\\.\\/\\\\]+/', $CFG->wwwroot.'/', $entry->audio, 1);
            echo format_text($entry->audio, FORMAT_MOODLE, (object)array('para'=>false));
            echo '</td>';
        }

        if ($show->concept) {
            echo '<td class="entry concept"'.$disable_copy.'>';
            glossary_print_entry_concept($entry);
            echo '</td>';
        }

        if ($show->definition) {
            echo '<td class="entry definition"'.$disable_copy.'>';
            glossary_print_entry_definition($entry, $glossary, $cm);
            echo '</td>';
        }

        if ($show->icons) {
            echo '<td class="entry icons">';
            $return = glossary_print_entry_lower_section($course, $cm, $glossary, $entry, $mode, $hook, $printicons, $ratings, $aliases);
            echo '</td>';
        }

        echo '</tr>';
        echo "</table>\n";

    } else {
        echo '<div style="text-align:center">';
        print_string('noentry', 'glossary');
        echo '</div>';
    }

    return $return;
}

function glossary_print_entry_stimulations($course, $cm, $glossary, $entry, $mode='', $hook='', $printicons=1, $ratings=NULL) {

    //Take out autolinking in definitions in print view
    $entry->definition = '<span class="nolink">'.$entry->definition.'</span>';

    //Call to view function (without icons, ratings and aliases) and return its result
    return glossary_show_entry_stimulations($course, $cm, $glossary, $entry, $mode, $hook, false, false, false);

}

function glossary_format_setup_name($stringname, $stringtext, $plugin='glossary') {
    global $CFG;

    // check if string already exists
    $strman = get_string_manager();
    if ($strman->string_exists($stringname, $plugin)) {
        return true;
    }

    // specify the path to the lang directory
    $path = "$CFG->dirroot/mod/$plugin/lang/en";
    if (! is_writeable($path)) {
        return false;
        // we have to abort here because
        // the string manager will ignore
        // new strings in the en_local files,
        // and only accept modified strings
        $path = "$CFG->dataroot/lang/en_local";
    }

    // create lang directory, if necessary
    if (! file_exists($path)) {
        if (! mkdir($path, $CFG->directorypermissions, true)) {
            return false;
        }
    }

    // check if string is already in local file
    $path .= "/$plugin.php";
    $string = array();
    if (file_exists($path)) {
        include($path);
    }

    if (isset($string[$stringname])) {
        // string already exists in the lang file
        // but is not currently in the lang cache
    } else {
        // create/update local lang file
        if (count($string)) {
            $content = file_get_contents($path);
            $content = preg_replace('/\s+(?:\?>\s*)?$/s', "\n", $content);
        } else {
            $content = '';
            $content .= "<?php\n";
            $content .= "defined('MOODLE_INTERNAL') || die();\n";
        }
        $content .= "\$string['$stringname'] = '$stringtext';\n";
        file_put_contents($path, $content);
    }

    // clear caches to make sure this string is included in the future
    $strman->reset_caches();

    // all done
    return true;
}

glossary_format_setup_name('displayformatstimulations', 'Stimulations glossary');
