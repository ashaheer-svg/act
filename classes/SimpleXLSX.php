<?php /** @noinspection MultiAssignmentUsageInspection */

namespace Shuchkin;

use SimpleXMLElement;

/**
 *    SimpleXLSX php class
 *    MS Excel 2007+ workbooks reader
 *
 * Copyright (c) 2012 - 2022 SimpleXLSX
 *
 * @category   SimpleXLSX
 * @package    SimpleXLSX
 * @copyright  Copyright (c) 2012 - 2022 SimpleXLSX (https://github.com/shuchkin/simplexlsx/)
 * @license    MIT
 */

class SimpleXLSX
{
    public static $CF = [ // Cell formats
        0 => 'General',
        1 => '0',
        2 => '0.00',
        3 => '#,##0',
        4 => '#,##0.00',
        9 => '0%',
        10 => '0.00%',
        11 => '0.00E+00',
        12 => '# ?/?',
        13 => '# ??/??',
        14 => 'mm-dd-yy',
        15 => 'd-mmm-yy',
        16 => 'd-mmm',
        17 => 'mmm-yy',
        18 => 'h:mm AM/PM',
        19 => 'h:mm:ss AM/PM',
        20 => 'h:mm',
        21 => 'h:mm:ss',
        22 => 'm/d/yy h:mm',

        37 => '#,##0 ;(#,##0)',
        38 => '#,##0 ;[Red](#,##0)',
        39 => '#,##0.00;(#,##0.00)',
        40 => '#,##0.00;[Red](#,##0.00)',

        44 => '_("$"* #,##0.00_);_("$"* \(#,##0.00\);_("$"* "-"??_);_(@_)',
        45 => 'mm:ss',
        46 => '[h]:mm:ss',
        47 => 'mmss.0',
        48 => '##0.0E+0',
        49 => '@',

        27 => '[$-404]e/m/d',
        30 => 'm/d/yy',
        36 => '[$-404]e/m/d',
        50 => '[$-404]e/m/d',
        57 => '[$-404]e/m/d',

        59 => 't0',
        60 => 't0.00',
        61 => 't#,##0',
        62 => 't#,##0.00',
        67 => 't0%',
        68 => 't0.00%',
        69 => 't# ?/?',
        70 => 't# ??/??',
    ];
    public $nf = []; // number formats
    public $cellFormats = []; // cellXfs
    public $datetimeFormat = 'Y-m-d H:i:s';
    public $debug;
    public $activeSheet = 0;
    public $rowsExReader;

    /* @var SimpleXMLElement[] $sheets */
    public $sheets;
    public $sheetFiles = [];
    public $sheetMetaData = [];
    public $sheetRels = [];
    // scheme
    public $styles;
    /* @var array[] $package */
    public $package;
    public $sharedstrings;
    public $date1904 = 0;

    public $errno = 0;
    public $error = false;
    /**
     * @var false|SimpleXMLElement
     */
    public $theme;


    public function __construct($filename = null, $is_data = null, $debug = null)
    {
        if ($debug !== null) {
            $this->debug = $debug;
        }
        $this->package = [
            'filename' => '',
            'mtime' => 0,
            'size' => 0,
            'comment' => '',
            'entries' => []
        ];
        if ($filename && $this->unzip($filename, $is_data)) {
            $this->parseEntries();
        }
    }

    public function unzip($filename, $is_data = false)
    {

        if ($is_data) {
            $this->package['filename'] = 'default.xlsx';
            $this->package['mtime'] = time();
            $this->package['size'] = self::strlen($filename);

            $vZ = $filename;
        } else {
            if (!is_readable($filename)) {
                $this->error(1, 'File not found ' . $filename);

                return false;
            }

            // Package information
            $this->package['filename'] = $filename;
            $this->package['mtime'] = filemtime($filename);
            $this->package['size'] = filesize($filename);

            // Read file
            $vZ = file_get_contents($filename);
        }
        // Explode to each part
        $aE = explode("\x50\x4b\x03\x04", $vZ);
        array_shift($aE);

        $aEL = count($aE);
        if ($aEL === 0) {
            $this->error(2, 'Unknown archive format');

            return false;
        }
        // Search central directory end record
        $last = $aE[$aEL - 1];
        $last = explode("\x50\x4b\x05\x06", $last);
        if (count($last) !== 2) {
            $this->error(2, 'Unknown archive format');

            return false;
        }
        // Search central directory
        $last = explode("\x50\x4b\x01\x02", $last[0]);
        if (count($last) < 2) {
            $this->error(2, 'Unknown archive format');

            return false;
        }
        $aE[$aEL - 1] = $last[0];

        // Loop through the entries
        foreach ($aE as $vZ) {
            $aI = [];
            $aI['E'] = 0;
            $aI['EM'] = '';
            // Retrieving local file header information
            $aP = unpack('v1VN/v1GPF/v1CM/v1FT/v1FD/V1CRC/V1CS/V1UCS/v1FNL/v1EFL', $vZ);

            $nF = $aP['FNL'];
            $mF = $aP['EFL'];

            // Special case : value block after the compressed data
            if ($aP['GPF'] & 0x0008) {
                $aP1 = unpack('V1CRC/V1CS/V1UCS', self::substr($vZ, -12));

                $aP['CRC'] = $aP1['CRC'];
                $aP['CS'] = $aP1['CS'];
                $aP['UCS'] = $aP1['UCS'];
                // 2013-08-10
                $vZ = self::substr($vZ, 0, -12);
                if (self::substr($vZ, -4) === "\x50\x4b\x07\x08") {
                    $vZ = self::substr($vZ, 0, -4);
                }
            }

            // Getting stored filename
            $aI['N'] = self::substr($vZ, 26, $nF);
            $aI['N'] = str_replace('\\', '/', $aI['N']);

            if (self::substr($aI['N'], -1) === '/') {
                // is a directory entry - will be skipped
                continue;
            }

            // Truncate full filename in path and filename
            $aI['P'] = dirname($aI['N']);
            $aI['P'] = ($aI['P'] === '.') ? '' : $aI['P'];
            $aI['N'] = basename($aI['N']);

            $vZ = self::substr($vZ, 26 + $nF + $mF);

            if ($aP['CS'] > 0 && (self::strlen($vZ) !== (int)$aP['CS'])) { // check only if availabled
                $aI['E'] = 1;
                $aI['EM'] = 'Compressed size is not equal with the value in header information.';
            }

            // DOS to UNIX timestamp
            $aI['T'] = mktime(
                ($aP['FT'] & 0xf800) >> 11,
                ($aP['FT'] & 0x07e0) >> 5,
                ($aP['FT'] & 0x001f) << 1,
                ($aP['FD'] & 0x01e0) >> 5,
                $aP['FD'] & 0x001f,
                (($aP['FD'] & 0xfe00) >> 9) + 1980
            );

            $this->package['entries'][] = [
                'data' => $vZ,
                'ucs' => (int)$aP['UCS'], // ucompresses size
                'cm' => $aP['CM'], // compressed method
                'cs' => isset($aP['CS']) ? (int) $aP['CS'] : 0, // compresses size
                'crc' => $aP['CRC'],
                'error' => $aI['E'],
                'error_msg' => $aI['EM'],
                'name' => $aI['N'],
                'path' => $aI['P'],
                'time' => $aI['T']
            ];
        } // end for each entries

        return true;
    }


    public function error($num = null, $str = null)
    {
        if ($num) {
            $this->errno = $num;
            $this->error = $str;
            if ($this->debug) {
                trigger_error(__CLASS__ . ': ' . $this->error, E_USER_WARNING);
            }
        }

        return $this->error;
    }

    public function parseEntries()
    {
        // Document data holders
        $this->sharedstrings = [];
        $this->sheets = [];
        // Read relations and search for officeDocument
        if ($relations = $this->getEntryXML('_rels/.rels')) {
            foreach ($relations->Relationship as $rel) {
                $rel_type = basename(trim((string)$rel['Type'])); // officeDocument
                $rel_target = self::getTarget('', (string)$rel['Target']); // /xl/workbook.xml or xl/workbook.xml

                if ($rel_type === 'officeDocument'
                    && $workbook = $this->getEntryXML($rel_target)
                ) {
                    $index_rId = []; // [0 => rId1]

                    $index = 0;
                    foreach ($workbook->sheets->sheet as $s) {
                        $a = [];
                        foreach ($s->attributes() as $k => $v) {
                            $a[(string)$k] = (string)$v;
                        }

                        $this->sheetMetaData[$index] = $a;
                        $index_rId[$index] = (string)$s['id'];
                        $index++;
                    }
                    if ((int)$workbook->workbookPr['date1904'] === 1) {
                        $this->date1904 = 1;
                    }


                    if ($workbookRelations = $this->getEntryXML(dirname($rel_target) . '/_rels/workbook.xml.rels')) {
                        // Loop relations for workbook and extract sheets...
                        foreach ($workbookRelations->Relationship as $workbookRelation) {
                            $wrel_type = basename(trim((string)$workbookRelation['Type'])); // worksheet
                            $wrel_target = self::getTarget(dirname($rel_target), (string)$workbookRelation['Target']);
                            if (!$this->entryExists($wrel_target)) {
                                continue;
                            }

                            if ($wrel_type === 'worksheet') { // Sheets
                                if ($sheet = $this->getEntryXML($wrel_target)) {
                                    $index = array_search((string)$workbookRelation['Id'], $index_rId, true);
                                    $this->sheets[$index] = $sheet;
                                    $this->sheetFiles[$index] = $wrel_target;
                                    $srel_d = dirname($wrel_target);
                                    $srel_f = basename($wrel_target);
                                    $srel_file = $srel_d . '/_rels/' . $srel_f  . '.rels';
                                    if ($this->entryExists($srel_file)) {
                                        $this->sheetRels[$index] = $this->getEntryXML($srel_file);
                                    }
                                }
                            } elseif ($wrel_type === 'sharedStrings') {
                                if ($sharedStrings = $this->getEntryXML($wrel_target)) {
                                    foreach ($sharedStrings->si as $val) {
                                        if (isset($val->t)) {
                                            $this->sharedstrings[] = (string)$val->t;
                                        } elseif (isset($val->r)) {
                                            $this->sharedstrings[] = self::parseRichText($val);
                                        }
                                    }
                                }
                            } elseif ($wrel_type === 'styles') {
                                $this->styles = $this->getEntryXML($wrel_target);

                                // number formats
                                $this->nf = [];
                                if (isset($this->styles->numFmts->numFmt)) {
                                    foreach ($this->styles->numFmts->numFmt as $v) {
                                        $this->nf[(int)$v['numFmtId']] = (string)$v['formatCode'];
                                    }
                                }

                                $this->cellFormats = [];
                                if (isset($this->styles->cellXfs->xf)) {
                                    foreach ($this->styles->cellXfs->xf as $v) {
                                        $x = [
                                            'format' => null
                                        ];
                                        foreach ($v->attributes() as $k1 => $v1) {
                                            $x[ $k1 ] = (int) $v1;
                                        }
                                        if (isset($x['numFmtId'])) {
                                            if (isset($this->nf[$x['numFmtId']])) {
                                                $x['format'] = $this->nf[$x['numFmtId']];
                                            } elseif (isset(self::$CF[$x['numFmtId']])) {
                                                $x['format'] = self::$CF[$x['numFmtId']];
                                            }
                                        }

                                        $this->cellFormats[] = $x;
                                    }
                                }
                            } elseif ($wrel_type === 'theme') {
                                $this->theme = $this->getEntryXML($wrel_target);
                            }

                        }

                    }
                    // reptile hack :: find active sheet from workbook.xml
                    if ($workbook->bookViews->workbookView) {
                        foreach ($workbook->bookViews->workbookView as $v) {
                            if (!empty($v['activeTab'])) {
                                $this->activeSheet = (int)$v['activeTab'];
                            }
                        }
                    }

                    break;
                }
            }
        }

        if (count($this->sheets)) {
            // Sort sheets
            ksort($this->sheets);

            return true;
        }

        return false;
    }

    public function getEntryXML($name)
    {
        if ($entry_xml = $this->getEntryData($name)) {
            $this->deleteEntry($name); // economy memory
            // dirty remove namespace prefixes and empty rows
            $entry_xml = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $entry_xml); // remove namespaces
            $entry_xml .= ' '; // force run garbage collector
            // remove namespaced attrs
            $entry_xml = preg_replace('/[a-zA-Z0-9]+:([a-zA-Z0-9]+="[^"]+")/', '$1', $entry_xml);
            $entry_xml .= ' ';
            $entry_xml = preg_replace('/<[a-zA-Z0-9]+:([^>]+)>/', '<$1>', $entry_xml); // fix namespaced openned tags
            $entry_xml .= ' ';
            $entry_xml = preg_replace('/<\/[a-zA-Z0-9]+:([^>]+)>/', '</$1>', $entry_xml); // fix namespaced closed tags
            $entry_xml .= ' ';

            if (strpos($name, '/sheet')) { // dirty skip empty rows
                // remove <row...> <c /><c /></row>
                $cnt = $cnt2 = $cnt3 = null;
                $entry_xml = preg_replace('/<row[^>]+>\s*(<c[^\/]+\/>\s*)+<\/row>/', '', $entry_xml, -1, $cnt);
                $entry_xml .= ' ';
                // remove <row />
                $entry_xml = preg_replace('/<row[^\/>]*\/>/', '', $entry_xml, -1, $cnt2);
                $entry_xml .= ' ';
                // remove <row...></row>
                $entry_xml = preg_replace('/<row[^>]*><\/row>/', '', $entry_xml, -1, $cnt3);
                $entry_xml .= ' ';
                if ($cnt || $cnt2 || $cnt3) {
                    $entry_xml = preg_replace('/<dimension[^\/]+\/>/', '', $entry_xml);
                    $entry_xml .= ' ';
                }
            }
            $entry_xml = trim($entry_xml);

            if (LIBXML_VERSION < 20900 && function_exists('libxml_disable_entity_loader')) {
                $_old = libxml_disable_entity_loader();
            }

            $_old_uie = libxml_use_internal_errors(true);

            $entry_xmlobj = simplexml_load_string($entry_xml, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_PARSEHUGE);

            libxml_use_internal_errors($_old_uie);

            if (LIBXML_VERSION < 20900 && function_exists('libxml_disable_entity_loader')) {
                libxml_disable_entity_loader($_old);
            }

            if ($entry_xmlobj) {
                return $entry_xmlobj;
            }
            $e = libxml_get_last_error();
            if ($e) {
                $this->error(3, 'XML-entry ' . $name . ' parser error ' . $e->message . ' line ' . $e->line);
            }
        } else {
            $this->error(4, 'XML-entry not found ' . $name);
        }

        return false;
    }

    public function getEntryData($name)
    {
        $name = ltrim(str_replace('\\', '/', $name), '/');
        $dir = self::strtoupper(dirname($name));
        $name = self::strtoupper(basename($name));
        foreach ($this->package['entries'] as &$entry) {
            if (self::strtoupper($entry['path']) === $dir && self::strtoupper($entry['name']) === $name) {
                if ($entry['error']) {
                    return false;
                }

                switch ($entry['cm']) {
                    case -1:
                    case 0: // Stored
                        break;
                    case 8: // Deflated
                        $entry['data'] = gzinflate($entry['data']);
                        break;
                    case 12: // BZIP2
                        if (extension_loaded('bz2')) {
                            $entry['data'] = bzdecompress($entry['data']);
                        } else {
                            $entry['error'] = 7;
                            $entry['error_message'] = 'PHP BZIP2 extension not available.';
                        }
                        break;
                    default:
                        $entry['error'] = 6;
                        $entry['error_msg'] = 'De-/Compression method '.$entry['cm'].' is not supported.';
                }
                if (!$entry['error'] && $entry['cm'] > -1) {
                    $entry['cm'] = -1;
                    if ($entry['data'] === false) {
                        $entry['error'] = 2;
                        $entry['error_msg'] = 'Decompression of data failed.';
                    } elseif ($entry['ucs'] > 0 && (self::strlen($entry['data']) !== (int)$entry['ucs'])) {
                        $entry['error'] = 3;
                        $entry['error_msg'] = 'Uncompressed size is not equal with the value in header information.';
                    } elseif (crc32($entry['data']) !== $entry['crc']) {
                        $entry['error'] = 4;
                        $entry['error_msg'] = 'CRC32 checksum is not equal with the value in header information.';
                    }
                }

                return $entry['data'];
            }
        }
        unset($entry);
        $this->error(5, 'Entry not found ' . ($dir ? $dir . '/' : '') . $name);

        return false;
    }

    public function deleteEntry($name)
    {
        $name = ltrim(str_replace('\\', '/', $name), '/');
        $dir = self::strtoupper(dirname($name));
        $name = self::strtoupper(basename($name));
        foreach ($this->package['entries'] as $k => $entry) {
            if (self::strtoupper($entry['path']) === $dir && self::strtoupper($entry['name']) === $name) {
                unset($this->package['entries'][$k]);
                return true;
            }
        }
        return false;
    }

    public static function strtoupper($str)
    {
        return (ini_get('mbstring.func_overload') & 2) ? mb_strtoupper($str, '8bit') : strtoupper($str);
    }

    public function entryExists($name)
    {
        $dir = self::strtoupper(dirname($name));
        $name = self::strtoupper(basename($name));
        foreach ($this->package['entries'] as $entry) {
            if (self::strtoupper($entry['path']) === $dir && self::strtoupper($entry['name']) === $name) {
                return true;
            }
        }
        return false;
    }

    public static function parse($filename, $is_data = false, $debug = false)
    {
        $xlsx = new self();
        $xlsx->debug = $debug;
        if ($xlsx->unzip($filename, $is_data)) {
            $xlsx->parseEntries();
        }
        if ($xlsx->success()) {
            return $xlsx;
        }

        return false;
    }

    public function success()
    {
        return !$this->error;
    }

    public function rows($worksheetIndex = 0)
    {
        if (($ws = $this->worksheet($worksheetIndex)) === false) {
            return [];
        }

        $rows = [];
        $curR = 0;

        foreach ($ws->sheetData->row as $row) {
            $r = (int)$row['r'];
            $curR = $r > 0 ? $r - 1 : $curR;
            
            $curC = 0;
            foreach ($row->c as $c) {
                $idx = $this->getIndex((string)$c['r']);
                $x = $idx[0];
                $y = $idx[1];

                $val = (string)$c->v;
                $t = (string)$c['t'];

                if ($t === 's') {
                    $val = isset($this->sharedstrings[$val]) ? $this->sharedstrings[$val] : $val;
                }

                $rows[$y][$x] = $val;
            }
            $curR++;
        }

        // Fill empty cells
        if (count($rows)) {
            $maxC = 0;
            foreach($rows as $r) {
                $maxC = max($maxC, count($r) ? max(array_keys($r)) : 0);
            }
            $maxR = max(array_keys($rows));
            
            for($y = 0; $y <= $maxR; $y++) {
                for($x = 0; $x <= $maxC; $x++) {
                    if (!isset($rows[$y][$x])) $rows[$y][$x] = '';
                }
                ksort($rows[$y]);
            }
        }
        
        return $rows;
    }

    public function worksheet($worksheetIndex = 0)
    {
        if (isset($this->sheets[$worksheetIndex])) {
            return $this->sheets[$worksheetIndex];
        }
        return false;
    }

    public function getIndex($cell)
    {
        if (preg_match('/([A-Z]+)(\d+)/', $cell, $matches)) {
            $col = $matches[1];
            $row = $matches[2];
            $colIdx = 0;
            for ($i = 0; $i < strlen($col); $i++) {
                $colIdx = $colIdx * 26 + (ord($col[$i]) - 64);
            }
            return [$colIdx - 1, $row - 1];
        }
        return [0, 0];
    }

    public static function getTarget($base, $target)
    {
        $target = ltrim(str_replace('\\', '/', $target), '/');
        if ($base) {
            $target = $base . '/' . $target;
        }
        return $target;
    }

    public static function parseRichText($is)
    {
        $value = '';
        if (isset($is->r)) {
            foreach ($is->r as $run) {
                $value .= (string)$run->t;
            }
        }
        return $value;
    }

    public static function strlen($str)
    {
        return (ini_get('mbstring.func_overload') & 2) ? mb_strlen($str, '8bit') : strlen($str);
    }

    public static function substr($str, $start, $length = null)
    {
        return (ini_get('mbstring.func_overload') & 2) ? mb_substr($str, $start, $length, '8bit') : substr($str, $start, $length);
    }
}

