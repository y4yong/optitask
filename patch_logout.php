<?php
$dirs = ['admin', 'manager', 'employee'];

$swal_script = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

$logout_script = <<<HTML
<script>
function confirmLogout(e) {
    if(e) e.preventDefault();
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Close Session?',
            text: "Are you sure you want to exit OptiTask?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#FF8FAB',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, Logout',
            background: '#FFF9FA',
            customClass: {
                popup: 'rounded-[2.5rem] border-2 border-pink-100',
                title: 'font-black text-[#1e293b]',
                confirmButton: 'rounded-xl px-6 py-3',
                cancelButton: 'rounded-xl px-6 py-3'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../logout.php';
            }
        });
    } else {
        if(confirm("Are you sure you want to exit OptiTask?")) {
            window.location.href = '../logout.php';
        }
    }
}
</script>
HTML;

foreach ($dirs as $dir) {
    $files = glob("$dir/*.php");
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $changed = false;

        // 1. Replace <a href="../logout.php"...>
        if (strpos($content, 'href="../logout.php"') !== false) {
            $content = str_replace('href="../logout.php"', 'href="#" onclick="confirmLogout(event)"', $content);
            $changed = true;
        }

        // 2. Add confirmLogout function if not exists
        if ($changed && strpos($content, 'function confirmLogout') === false) {
            $content = str_replace('</body>', $logout_script . "\n</body>", $content);
        }
        
        // 3. Add sweetalert2 if missing
        if ($changed && strpos($content, 'sweetalert2') === false && strpos($content, '</head>') !== false) {
            $content = str_replace('</head>', $swal_script . "\n</head>", $content);
        }
        
        if ($changed) {
            file_put_contents($file, $content);
            echo "Patched: $file\n";
        }
    }
}

// Now handle the ones that have logout-btn
foreach ($dirs as $dir) {
    $files = glob("$dir/*.php");
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $changed = false;

        if (strpos($content, 'id="logout-btn"') !== false && strpos($content, 'onclick="confirmLogout(event)"') === false) {
            $content = str_replace('id="logout-btn"', 'id="logout-btn" onclick="confirmLogout(event)"', $content);
            $changed = true;
        }

        // Remove the existing JS event listener for logout-btn if it exists
        $pattern = "/document\.getElementById\('logout-btn'\)\.addEventListener\('click',\s*function\(\)\s*\{.*?window\.location\.href\s*=\s*'..\/logout.php';\s*\}\s*\);\s*\}\);/is";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, '', $content);
            $changed = true;
        }
        // another pattern because sometimes it doesn't have the closing });
        $pattern2 = "/document\.getElementById\('logout-btn'\)\.addEventListener\('click',\s*function\(\)\s*\{.*?window\.location\.href\s*=\s*'..\/logout.php';\s*\}\s*\);/is";
        if (preg_match($pattern2, $content)) {
            $content = preg_replace($pattern2, '', $content);
            $changed = true;
        }

        // Add script if missing
        if ($changed && strpos($content, 'function confirmLogout') === false) {
             $content = str_replace('</body>', $logout_script . "\n</body>", $content);
        }

        if ($changed) {
            file_put_contents($file, $content);
            echo "Patched Button: $file\n";
        }
    }
}
echo "Done.";
?>
