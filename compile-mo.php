<?php
// Simple MO compiler script
require_once 'vendor/autoload.php';

// Manual MO compilation using POMO classes
class SimpleMOCompiler {
    public function compile() {
        $po_file = __DIR__ . '/languages/osq-stress-check-ja.po';
        $mo_file = __DIR__ . '/languages/osq-stress-check-ja.mo';
        
        if (!file_exists($po_file)) {
            die("PO file not found: $po_file\n");
        }
        
        // Parse PO file manually
        $content = file_get_contents($po_file);
        $entries = $this->parse_po_content($content);
        
        // Create MO binary format
        $mo_binary = $this->create_mo_binary($entries);
        
        // Write MO file
        file_put_contents($mo_file, $mo_binary);
        
        echo "Successfully compiled $mo_file\n";
        echo "Total entries: " . count($entries) . "\n";
    }
    
    private function parse_po_content($content) {
        $entries = [];
        $lines = explode("\n", $content);
        $current_msgid = '';
        $current_msgstr = '';
        $in_msgid = false;
        $in_msgstr = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'msgid "') === 0) {
                if ($current_msgid && $current_msgstr) {
                    $entries[$current_msgid] = $current_msgstr;
                }
                $current_msgid = substr($line, 7, -1);
                $current_msgstr = '';
                $in_msgid = true;
                $in_msgstr = false;
            } elseif (strpos($line, 'msgstr "') === 0) {
                $current_msgstr = substr($line, 8, -1);
                $in_msgid = false;
                $in_msgstr = true;
            } elseif (strpos($line, '"') === 0 && substr($line, -1) === '"') {
                $text = substr($line, 1, -1);
                if ($in_msgid) {
                    $current_msgid .= $text;
                } elseif ($in_msgstr) {
                    $current_msgstr .= $text;
                }
            }
        }
        
        // Add the last entry
        if ($current_msgid && $current_msgstr) {
            $entries[$current_msgid] = $current_msgstr;
        }
        
        return $entries;
    }
    
    private function create_mo_binary($entries) {
        $header = "MO\x00\x00"; // Magic bytes
        $revision = "\x00\x00\x00\x00"; // Revision
        $count = pack('V', count($entries)); // Number of strings
        
        // Calculate offsets
        $offset_base = 28; // Header size
        $hash_table_size = 0; // No hash table
        $hash_table_offset = 0;
        
        // String table offset calculation
        $original_table_offset = $offset_base;
        $translation_table_offset = $offset_base + (count($entries) * 8);
        
        // Build binary
        $binary = $header . $revision . $count . 
                  pack('V', $original_table_offset) . 
                  pack('V', $translation_table_offset) . 
                  pack('V', $hash_table_size) . 
                  pack('V', $hash_table_offset);
        
        // Build string tables
        $original_strings = '';
        $translation_strings = '';
        $original_offsets = '';
        $translation_offsets = '';
        
        $original_pos = 0;
        $translation_pos = 0;
        
        foreach ($entries as $msgid => $msgstr) {
            if ($msgid === '') continue; // Skip header
            
            // Original strings table
            $original_strings .= $msgid . "\x00";
            $original_offsets .= pack('V', strlen($msgid)) . pack('V', $original_pos);
            $original_pos += strlen($msgid) + 1;
            
            // Translation strings table
            $translation_strings .= $msgstr . "\x00";
            $translation_offsets .= pack('V', strlen($msgstr)) . pack('V', $translation_pos);
            $translation_pos += strlen($msgstr) + 1;
        }
        
        // Append tables
        $binary .= $original_offsets . $translation_offsets . $original_strings . $translation_strings;
        
        return $binary;
    }
}

$compiler = new SimpleMOCompiler();
$compiler->compile();