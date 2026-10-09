<?php
$dir = new RecursiveDirectoryIterator('.');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.html$/i', RecursiveRegexIterator::GET_MATCH);

foreach($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    
    $content = preg_replace_callback('/(<a class="navbar-brand fw-bold" href="[^"]*">)(.*?)(<\/a>)/s', function($matches) {
        $start = $matches[1];
        $inner = $matches[2];
        $end = $matches[3];
        
        $newInner = '<i class="bi bi-bank me-2"></i>Welfare Portal';
        if (strpos($inner, 'badge') !== false) {
            $newInner .= ' <span class="badge bg-warning text-dark ms-2 small">ADMIN</span>';
        }
        return $start . $newInner . $end;
    }, $content);
    
    file_put_contents($path, $content);
}
echo "Done";
?>
