<?php
/**
 * Pure PHP APK Parser
 * Reads AndroidManifest.xml binary format & extracts metadata and app icon.
 * No external tools (aapt, java) required — works on 100% of free hosting.
 */

class ApkParser {
    private $apkPath;
    private $zip;

    public function __construct($apkPath) {
        $this->apkPath = $apkPath;
    }

    public function parse() {
        if (!class_exists('ZipArchive')) {
            return [
                'success' => false,
                'error' => 'ZipArchive PHP extension is required.'
            ];
        }

        $this->zip = new ZipArchive();
        if ($this->zip->open($this->apkPath) !== true) {
            return [
                'success' => false,
                'error' => 'Failed to open APK file as ZIP archive.'
            ];
        }

        $manifestData = $this->zip->getFromName('AndroidManifest.xml');
        if (!$manifestData) {
            $this->zip->close();
            return [
                'success' => false,
                'error' => 'AndroidManifest.xml not found inside APK.'
            ];
        }

        $metadata = [
            'package_name' => '',
            'version_code' => 1,
            'version_name' => '1.0',
            'app_name' => '',
            'icon_path' => '',
            'file_size' => filesize($this->apkPath),
            'sha256' => hash_file('sha256', $this->apkPath)
        ];

        try {
            $parsed = $this->parseBinaryXml($manifestData);
            if (!empty($parsed['package'])) {
                $metadata['package_name'] = $parsed['package'];
            }
            if (!empty($parsed['versionCode'])) {
                $metadata['version_code'] = (int)$parsed['versionCode'];
            }
            if (!empty($parsed['versionName'])) {
                $metadata['version_name'] = $parsed['versionName'];
            }
            if (!empty($parsed['label'])) {
                $metadata['app_name'] = $parsed['label'];
            }

            // Extract Icon
            $iconPath = $this->extractIcon($parsed['icon'] ?? '');
            if ($iconPath) {
                $metadata['icon_path'] = $iconPath;
            }
        } catch (Exception $e) {
            // Soft failure: Fallback to basic metadata
            error_log("APK Parsing notice: " . $e->getMessage());
        }

        $this->zip->close();
        return array_merge(['success' => true], $metadata);
    }

    private function parseBinaryXml($data) {
        $length = strlen($data);
        if ($length < 8) return [];

        $header = unpack('vtype/vheaderSize/Vsize', substr($data, 0, 8));
        if ($header['type'] !== 0x0003) {
            return []; // Not RES_XML_TYPE
        }

        $offset = 8;
        $strings = [];

        while ($offset < $length) {
            if ($offset + 8 > $length) break;
            $chunk = unpack('vtype/vheaderSize/Vsize', substr($data, $offset, 8));
            $chunkType = $chunk['type'];
            $chunkSize = $chunk['size'];

            if ($chunkSize <= 0) break;

            if ($chunkType === 0x0001) { // RES_STRING_POOL_TYPE
                $strings = $this->parseStringPool(substr($data, $offset, $chunkSize));
            } elseif ($chunkType === 0x0102) { // RES_XML_START_ELEMENT_TYPE
                $res = $this->parseElementAttributes($data, $offset, $strings);
                if (!empty($res['package'])) {
                    return $res;
                }
            }

            $offset += $chunkSize;
        }

        return [];
    }

    private function parseStringPool($chunkData) {
        $pool = unpack('vtype/vheaderSize/VchunkSize/VstringCount/VstyleCount/Vflags/VstringsStart/VstylesStart', substr($chunkData, 0, 28));
        $stringCount = $pool['stringCount'];
        $flags = $pool['flags'];
        $stringsStart = $pool['stringsStart'];
        $isUtf8 = ($flags & (1 << 8)) !== 0;

        $offsets = [];
        for ($i = 0; $i < $stringCount; $i++) {
            $off = unpack('Voffset', substr($chunkData, 28 + ($i * 4), 4));
            $offsets[] = $off['offset'];
        }

        $strings = [];
        for ($i = 0; $i < $stringCount; $i++) {
            $strPos = $stringsStart + $offsets[$i];
            if ($strPos >= strlen($chunkData)) {
                $strings[] = '';
                continue;
            }

            if ($isUtf8) {
                // UTF-8 length prefix
                $u16len = ord($chunkData[$strPos++]);
                if ($u16len & 0x80) $strPos++;
                $u8len = ord($chunkData[$strPos++]);
                if ($u8len & 0x80) $strPos++;
                $strings[] = substr($chunkData, $strPos, $u8len);
            } else {
                // UTF-16 length prefix
                $len = unpack('v', substr($chunkData, $strPos, 2))[1];
                $strPos += 2;
                if ($len & 0x8000) $strPos += 2;
                $strBytes = substr($chunkData, $strPos, $len * 2);
                $strings[] = mb_convert_encoding($strBytes, 'UTF-8', 'UTF-16LE');
            }
        }

        return $strings;
    }

    private function parseElementAttributes($data, $offset, &$strings) {
        $result = [];
        $header = unpack('vtype/vheaderSize/Vsize/Vline/Vcomment/Vns/Vname/vattrStart/vattrSize/vattrCount', substr($data, $offset, 32));
        $attrCount = $header['attrCount'];
        $attrOffset = $offset + 32;

        for ($i = 0; $i < $attrCount; $i++) {
            $attr = unpack('Vns/Vname/VvalStr/Vtype/Vdata', substr($data, $attrOffset + ($i * 20), 20));
            $name = $strings[$attr['name']] ?? '';
            $val = '';

            if ($attr['valStr'] !== 0xFFFFFFFF && isset($strings[$attr['valStr']])) {
                $val = $strings[$attr['valStr']];
            } else {
                $val = $attr['data'];
            }

            if ($name === 'package') {
                $result['package'] = $val;
            } elseif ($name === 'versionCode') {
                $result['versionCode'] = $val;
            } elseif ($name === 'versionName') {
                $result['versionName'] = $val;
            } elseif ($name === 'label') {
                $result['label'] = $val;
            } elseif ($name === 'icon') {
                $result['icon'] = $val;
            }
        }

        return $result;
    }

    private function extractIcon($iconResId) {
        // Look through common icon file patterns inside APK zip
        $candidates = [
            'res/mipmap-xxxhdpi/ic_launcher.png',
            'res/mipmap-xxhdpi/ic_launcher.png',
            'res/mipmap-xhdpi/ic_launcher.png',
            'res/mipmap-hdpi/ic_launcher.png',
            'res/drawable-xxhdpi/icon.png',
            'res/drawable/icon.png',
            'res/drawable/app_icon.png',
            'res/mipmap-mdpi/ic_launcher.png'
        ];

        // Search zip entries
        $foundName = null;
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $filename = $this->zip->getNameIndex($i);
            if (preg_match('#(mipmap|drawable).*(ic_launcher|icon|app_icon)\.(png|webp)$#i', $filename)) {
                $foundName = $filename;
                if (strpos($filename, 'xxhdpi') !== false || strpos($filename, 'xxxhdpi') !== false) {
                    break; // High resolution preferred
                }
            }
        }

        if (!$foundName) {
            foreach ($candidates as $cand) {
                if ($this->zip->locateName($cand) !== false) {
                    $foundName = $cand;
                    break;
                }
            }
        }

        if ($foundName) {
            $iconData = $this->zip->getFromName($foundName);
            if ($iconData) {
                $ext = pathinfo($foundName, PATHINFO_EXTENSION) ?: 'png';
                $destName = 'icon_' . uniqid() . '.' . $ext;
                $destPath = UPLOAD_DIR_ICONS . '/' . $destName;
                if (@file_put_contents($destPath, $iconData)) {
                    return 'uploads/icons/' . $destName;
                }
            }
        }

        return '';
    }
}
