<?php
require_once 'config.php';
require_once 'GeminiAPI.php';

session_start();

$gemini = new GeminiAPI(GEMINI_API_KEY);

// Debug için
error_log("POST data: " . print_r($_POST, true));
error_log("SESSION data: " . print_r($_SESSION, true));

// Kategori değiştirildiğinde veya süre dolduğunda
if (isset($_POST['kategori'])) {
    $yeni_kategori = $_POST['kategori'];
    
    // Boş kategori seçimi kontrolü
    if (!empty($yeni_kategori)) {
        // Session'ı temizle
        unset($_SESSION['current_question']);
        unset($_SESSION['siklar']);
        unset($_SESSION['dogru_cevap']);
        unset($_SESSION['start_time']);
        
        // Yeni kategoriyi kaydet
        $_SESSION['kategori'] = $yeni_kategori;
        
        // Yeni soru al
        try {
            $prompt = "Lütfen {$yeni_kategori} kategorisinde orta zorlukta bir soru ve 4 şık hazırla. Yanıtı şu formatta ver:
            SORU: [soru metni]
            A) [şık A]
            B) [şık B]
            C) [şık C]
            D) [şık D]
            DOĞRU CEVAP: [A, B, C veya D]";

            $yanit = $gemini->soruSor($prompt);
            error_log("API Response: " . $yanit);
            
            if ($yanit) {
                $lines = explode("\n", $yanit);
                $soru = '';
                $siklar = [];
                $dogru_cevap = '';
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'SORU:') === 0) {
                        $soru = trim(substr($line, 5));
                    } elseif (preg_match('/^([A-D]\))(.+)$/', $line, $matches)) {
                        $sik_harf = substr($matches[1], 0, 1);
                        $siklar[$sik_harf] = trim($matches[2]);
                    } elseif (strpos($line, 'DOĞRU CEVAP:') === 0) {
                        $dogru_cevap = strtoupper(trim(substr($line, 12)));
                        $dogru_cevap = preg_replace('/[^A-D]/', '', $dogru_cevap);
                    }
                }
                
                if (!empty($dogru_cevap) && in_array($dogru_cevap, ['A', 'B', 'C', 'D'])) {
                    $_SESSION['current_question'] = $soru;
                    $_SESSION['siklar'] = $siklar;
                    $_SESSION['dogru_cevap'] = $dogru_cevap;
                    $_SESSION['start_time'] = time();
                    
                    error_log("Soru yüklendi: " . $soru);
                    error_log("Şıklar: " . print_r($siklar, true));
                    error_log("Doğru cevap: " . $dogru_cevap);
                }
            }
        } catch (Exception $e) {
            error_log("Hata: " . $e->getMessage());
        }
    }
}

// Süre dolduğunda
if (isset($_POST['sure_doldu'])) {
    $kategori = $_SESSION['kategori'];
    $_POST['kategori'] = $kategori;
}

// Kullanıcı cevap verdiğinde
if (isset($_POST['kullanici_cevap'])) {
    $kullanici_cevap = strtoupper(trim($_POST['kullanici_cevap']));
    $kullanici_cevap = preg_replace('/[^A-D]/', '', $kullanici_cevap);
    $gecen_sure = time() - $_SESSION['start_time'];
    
    error_log("Kullanıcı cevabı: " . $kullanici_cevap);
    error_log("Doğru cevap: " . $_SESSION['dogru_cevap']);
    
    if ($gecen_sure > 30) {
        $sonuc = "Süre doldu! Yeni bir soru alabilirsiniz.";
        $sonuc_class = "text-red-600";
    } else {
        if ($kullanici_cevap === $_SESSION['dogru_cevap']) {
            $sonuc = "Tebrikler! Doğru cevap verdiniz. Süre: {$gecen_sure} saniye";
            $sonuc_class = "text-green-600";
        } else {
            $sonuc = "Üzgünüm, yanlış cevap. Doğru cevap: " . $_SESSION['dogru_cevap'];
            $sonuc_class = "text-red-600";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Bilgi Yarışması</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">AI Bilgi Yarışması</h1>
            <p class="text-gray-600">Bilginizi test edin!</p>
        </div>

        <!-- Timer -->
        <?php if (isset($_SESSION['current_question']) && !isset($sonuc)): ?>
        <div id="timer" class="fixed top-4 right-4 bg-white rounded-lg shadow-lg p-4 text-center">
            <div class="text-xl font-bold">Kalan Süre</div>
            <div id="countdown" class="text-2xl text-blue-600">30</div>
        </div>
        <?php endif; ?>

        <!-- Kategori Seçimi -->
        <?php if (!isset($_SESSION['current_question'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Kategori Seçin</h2>
            <form method="POST" class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <button type="submit" name="kategori" value="tarih" class="bg-blue-100 hover:bg-blue-200 p-4 rounded-lg">
                    <i class="fas fa-history mb-2"></i>
                    <span class="block">Tarih</span>
                </button>
                <button type="submit" name="kategori" value="spor" class="bg-green-100 hover:bg-green-200 p-4 rounded-lg">
                    <i class="fas fa-futbol mb-2"></i>
                    <span class="block">Spor</span>
                </button>
                <button type="submit" name="kategori" value="bilim" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg">
                    <i class="fas fa-atom mb-2"></i>
                    <span class="block">Bilim</span>
                </button>
                <button type="submit" name="kategori" value="sanat" class="bg-yellow-100 hover:bg-yellow-200 p-4 rounded-lg">
                    <i class="fas fa-palette mb-2"></i>
                    <span class="block">Sanat</span>
                </button>
                <button type="submit" name="kategori" value="coğrafya" class="bg-red-100 hover:bg-red-200 p-4 rounded-lg">
                    <i class="fas fa-globe-americas mb-2"></i>
                    <span class="block">Coğrafya</span>
                </button>
                <button type="submit" name="kategori" value="genel kültür" class="bg-indigo-100 hover:bg-indigo-200 p-4 rounded-lg">
                    <i class="fas fa-brain mb-2"></i>
                    <span class="block">Genel Kültür</span>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Soru ve Cevap Alanı -->
        <?php if (isset($_SESSION['current_question']) && isset($_SESSION['siklar'])): ?>
        <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="mb-6">
                <!-- Kategori Seçimi ve Mevcut Kategori Gösterimi -->
                <div class="flex justify-between items-center mb-4">
                    <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                        <?php echo htmlspecialchars($_SESSION['kategori']); ?>
                    </span>
                    <div class="relative">
                        <form method="POST" class="inline">
                            <select name="kategori" onchange="this.form.submit()" 
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5">
                                <option value="">Kategori Değiştir</option>
                                <option value="tarih" <?php echo ($_SESSION['kategori'] == 'tarih') ? 'selected' : ''; ?>>Tarih</option>
                                <option value="spor" <?php echo ($_SESSION['kategori'] == 'spor') ? 'selected' : ''; ?>>Spor</option>
                                <option value="bilim" <?php echo ($_SESSION['kategori'] == 'bilim') ? 'selected' : ''; ?>>Bilim</option>
                                <option value="sanat" <?php echo ($_SESSION['kategori'] == 'sanat') ? 'selected' : ''; ?>>Sanat</option>
                                <option value="coğrafya" <?php echo ($_SESSION['kategori'] == 'coğrafya') ? 'selected' : ''; ?>>Coğrafya</option>
                                <option value="genel kültür" <?php echo ($_SESSION['kategori'] == 'genel kültür') ? 'selected' : ''; ?>>Genel Kültür</option>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Soru -->
                <div class="text-gray-700 mb-4">
                    <h3 class="text-xl font-semibold mb-2">Soru:</h3>
                    <p><?php echo htmlspecialchars($_SESSION['current_question']); ?></p>
                </div>
            </div>

            <!-- Şıklar -->
            <form method="POST" id="answerForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($_SESSION['siklar'] as $harf => $metin): ?>
                    <button type="submit" name="kullanici_cevap" value="<?php echo $harf; ?>" 
                        class="p-4 text-left rounded-lg border border-gray-300 hover:bg-blue-50 transition-colors">
                        <span class="font-semibold"><?php echo $harf; ?></span>) <?php echo htmlspecialchars($metin); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </form>

            <?php if (isset($sonuc)): ?>
            <div class="mt-6 p-4 rounded-lg bg-gray-50">
                <p class="<?php echo $sonuc_class; ?> font-semibold"><?php echo $sonuc; ?></p>
                <form method="POST" class="mt-4">
                    <button type="submit" name="kategori" value="<?php echo $_SESSION['kategori']; ?>" 
                        class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                        Yeni Soru
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="text-center text-gray-500 text-sm">
            <p>© 2024 AI Bilgi Yarışması. Tüm hakları saklıdır.</p>
        </footer>
    </div>

    <?php if (isset($_SESSION['current_question']) && !isset($sonuc)): ?>
    <script>
    let timeLeft = 30;
    const countdownElement = document.getElementById('countdown');
    const answerForm = document.getElementById('answerForm');

    const timer = setInterval(() => {
        timeLeft--;
        countdownElement.textContent = timeLeft;
        
        if (timeLeft <= 10) {
            countdownElement.classList.add('text-red-600');
        }
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="sure_doldu" value="1">' +
                           '<input type="hidden" name="kategori" value="<?php echo $_SESSION['kategori']; ?>">';
            document.body.appendChild(form);
            form.submit();
        }
    }, 1000);

    answerForm.addEventListener('submit', () => {
        clearInterval(timer);
    });
    </script>
    <?php endif; ?>
</body>
</html>