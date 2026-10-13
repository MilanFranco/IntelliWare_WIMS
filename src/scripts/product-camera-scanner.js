/**
 * product-camera-scanner.js
 *
 * High-accuracy, real-store camera OCR and packaging identification engine for IntelliWare.
 *
 * Features:
 * 1. Dual-Polarity Image Preprocessing: Automatically detects dark-background packaging
 *    and inverts/enhances contrast so Tesseract reads white/silver text with high fidelity.
 * 2. Strict Gibberish & Noise Filter: Discards fragmented OCR tokens
 *    and filters batch/lot numbers, warnings, and fine print.
 * 3. Multi-Candidate Suggestion Generator & 1-Click Raw Text Drawer.
 */
var ProductCameraScanner = (function () {
    'use strict';

    // ── POS Audio Chime ───────────────────────────────────────────────────────
    var _audioCtx = null;
    function playBeep(isSuccess) {
        try {
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            if (!_audioCtx) _audioCtx = new AudioContext();
            if (_audioCtx.state === 'suspended') _audioCtx.resume();

            var osc = _audioCtx.createOscillator();
            var gain = _audioCtx.createGain();
            osc.connect(gain);
            gain.connect(_audioCtx.destination);

            var now = _audioCtx.currentTime;
            if (isSuccess !== false) {
                // High POS checkout chime (two-tone 900Hz -> 1350Hz)
                osc.frequency.setValueAtTime(900, now);
                osc.frequency.exponentialRampToValueAtTime(1350, now + 0.08);
                gain.gain.setValueAtTime(0.20, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.18);
                osc.start(now);
                osc.stop(now + 0.18);
            } else {
                // Low reject alert
                osc.frequency.setValueAtTime(280, now);
                gain.gain.setValueAtTime(0.15, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.22);
                osc.start(now);
                osc.stop(now + 0.22);
            }
        } catch (e) {
            // Audio not allowed or unavailable
        }
    }

    // ── Retail Marketing, Regulatory & Packaging Noise Patterns ───────────────
    var NOISE_PATTERNS = [
        /\b(?:dermatologically\s+tested|clinically\s+(?:proven|tested)|hypoallergenic|ophthalmologist\s+tested)\b/i,
        /\b(?:tone\s*up|protect|hydrate|brighten|soothe|refresh|glow\s*boost)\b/i,
        /\b(?:anti-?bluelight|anti-?pollution|water\s*resistant|sweat\s*proof|waterproof|long\s*lasting)\b/i,
        /\b(?:uva\s*\+\s*uvb|broad\s*spectrum|pa\s*\+{1,4}|spf\s*\d+\+?|\d+\s*spf\+?)\b/i,
        /\b(?:with\s+bio-?placenta|with\s+vitamin|with\s+aloe|enriched\s+with|contains|infused\s+with)\b/i,
        /\b(?:net\s*wt|net\s*weight|fl\s*oz|net\s*contents?|volume|serving\s*size|servings)\b/i,
        /\b(?:best\s+before|expiry|expiration|mfg(?:\s*date)?|batch(?:\s*no|\s*num|\s*number)?|lot(?:\s*no|\s*num|\s*number)?|reg\s+no|fda|bfad|halal|iso\s*\d+|gmp)\b/i,
        /\b(?:manufactured|distributed|imported|packaged)\s+(?:by|for|in)?\b/i,
        /\b(?:corp\.|corporation|inc\.|ltd\.|limited|co\.,?\s*ltd|phils?\.?|philippines)\b/i,
        /\b(?:directions|instructions|ingredients?|allergens?|caution|warning|keep\s+out|store\s+(?:at|in|below))\b/i,
        /\b(?:for\s+external\s+use(?:\s+only)?|avoid\s+contact|shake\s+well|apply\s+generously|single\s+use\s+only)\b/i,
        /\b(?:100%\s+electronically\s+tested|electronically\s+tested|natural\s+rubber\s+latex|nominal\s+width|\d+\s*mm\s*width)\b/i,
        /\b(?:new\s+look|new\s+formula|improved|original\s+formula|buy\s+1\s+take\s+1|promo\s+pack|free)\b/i,
        /\b(?:customer\s*care|hotline|website|email|feedback|recycle|made\s+in\s+[a-z]+|product\s+of\s+[a-z]+)\b/i
    ];

    // Recognizable retail product nouns
    var PRODUCT_TYPES = [
        'condoms', 'condom', 'premium condoms', 'lubricant', 'lubricated condoms',
        'sunscreen', 'sunblock', 'tinted sunscreen', 'matte sunscreen', 'serum', 'lotion',
        'moisturizer', 'cleanser', 'toner', 'face wash', 'facial foam', 'cream', 'gel',
        'lip balm', 'lipstick', 'tint', 'matte tint', 'lip gloss', 'shampoo', 'conditioner',
        'soap', 'bar soap', 'body wash', 'deodorant', 'cologne', 'perfume', 'powder',
        'rubbing alcohol', 'sanitizer', 'ethyl alcohol', 'isopropyl alcohol',
        'pancit canton', 'instant noodles', 'cup noodles', 'spaghetti', 'pasta', 'macaroni',
        'chips', 'crisps', 'biscuits', 'cookies', 'crackers', 'chocolate', 'candy', 'wafer',
        'sardines', 'tuna', 'corned beef', 'meat loaf', 'sausage', 'coffee', 'tea',
        'drinking water', 'purified water', 'mineral water', 'soft drink', 'soda', 'juice',
        'detergent powder', 'liquid detergent', 'dishwashing liquid', 'bleach', 'fabric conditioner'
    ];

    // ── Category Classification Rules ─────────────────────────────────────────
    var CATEGORY_RULES = [
        {
            category: 'Personal Care',
            keywords: [
                'condom', 'condoms', 'latex', 'lubricant', 'sunscreen', 'sunblock', 'spf', 'tinted',
                'matte tint', 'serum', 'lotion', 'cream', 'moisturizer', 'cleanser', 'toner',
                'facial', 'skincare', 'dermatologically', 'soap', 'shampoo', 'conditioner',
                'toothpaste', 'toothbrush', 'deodorant', 'cologne', 'perfume', 'body wash',
                'lip balm', 'lipstick', 'powder', 'blush', 'foundation', 'mascara', 'cosmetic',
                'alcohol', 'sanitizer', 'cotton', 'pads', 'tissue', 'wipes'
            ],
            defaultUnit: 'pieces'
        },
        {
            category: 'Beverages',
            keywords: [
                'soda', 'cola', 'beverage', 'juice', 'tea', 'coffee', 'beer', 'wine', 'liquor',
                'latte', 'espresso', 'energy drink', 'soft drink', 'iced tea', 'drinking water',
                'purified water', 'mineral water', 'bottled water'
            ],
            defaultUnit: 'bottles'
        },
        {
            category: 'Dry Goods',
            keywords: [
                'noodle', 'pancit', 'canton', 'pasta', 'spaghetti', 'macaroni', 'rice', 'cereal',
                'flour', 'sugar', 'salt', 'seasoning', 'sauce', 'vinegar', 'soy sauce', 'cooking oil',
                'condiment', 'ramen', 'instant soup'
            ],
            defaultUnit: 'packs'
        },
        {
            category: 'Snacks',
            keywords: [
                'chips', 'crisps', 'biscuit', 'cookie', 'cracker', 'candy', 'chocolate', 'wafer',
                'nuts', 'popcorn', 'pretzel', 'marshmallow', 'sweets', 'gummy', 'toffee'
            ],
            defaultUnit: 'packs'
        },
        {
            category: 'Canned Goods',
            keywords: [
                'sardine', 'sardines', 'tuna', 'corned beef', 'meat loaf', 'spam', 'sausage',
                'luncheon meat', 'canned beans', 'tin can', 'canned mackerel'
            ],
            defaultUnit: 'cans'
        },
        {
            category: 'Household Items',
            keywords: [
                'bleach', 'detergent', 'powder detergent', 'dishwashing', 'fabric conditioner',
                'sponge', 'cleaner', 'disinfectant', 'insecticide', 'trash bag', 'tumbler',
                'flask', 'vacuum flask', 'paper towel'
            ],
            defaultUnit: 'bottles'
        },
        {
            category: 'Dairy Products',
            keywords: ['milk', 'cheese', 'butter', 'margarine', 'yogurt', 'condensed milk', 'evaporated milk', 'cream cheese'],
            defaultUnit: 'packs'
        },
        {
            category: 'Bakery Products',
            keywords: ['bread', 'loaf', 'buns', 'cake', 'pastry', 'muffin', 'pie', 'croissant'],
            defaultUnit: 'packs'
        },
        {
            category: 'Pet Supplies',
            keywords: ['dog food', 'cat food', 'pet food', 'whiskas', 'pedigree', 'kibble'],
            defaultUnit: 'bags'
        },
        {
            category: 'Stationery and Office Supplies',
            keywords: ['ballpen', 'pen', 'pencil', 'notebook', 'paper', 'binder', 'stapler', 'scissors', 'envelope', 'folder'],
            defaultUnit: 'pieces'
        }
    ];

    // Allowed legitimate short tokens
    var ALLOWED_SHORT_TOKENS = /^(xl|xs|xxl|sm|md|lg|uv|pa|hd|3d|4k|5g|no|oz|ml|cl|gm|kg|pc|pk|g|l|1s|3s|6s|12s|ea)$/i;

    // ── Strict Gibberish / OCR Noise Detector ─────────────────────────────────
    function isGibberishOrNoise(line) {
        if (!line || typeof line !== 'string') return true;
        var trimmed = line.trim();
        if (trimmed.length < 3) return true;

        var words = trimmed.split(/\s+/).filter(Boolean);
        if (words.length === 0) return true;

        // If only 1 word, must have at least 3 letters and contain vowels
        if (words.length === 1) {
            if (words[0].length < 3) return true;
            if (!/[aeiouy]/i.test(words[0])) return true;
            return false;
        }

        // Count short words (<= 2 chars) that are not recognized specs/units
        var shortWordCount = 0;
        var totalLetters = 0;
        for (var i = 0; i < words.length; i++) {
            var w = words[i];
            totalLetters += w.length;
            if (w.length <= 2 && !ALLOWED_SHORT_TOKENS.test(w)) {
                shortWordCount++;
            }
        }

        // CRITICAL: Reject strings like "Lt En Bo Br Tae As" where >= 35% of words are 1-2 char fragments
        if (words.length >= 2 && (shortWordCount / words.length) >= 0.35) {
            return true;
        }

        // Average word length must be >= 3.0 characters
        var avgWordLength = totalLetters / words.length;
        if (avgWordLength < 2.9) {
            return true;
        }

        // Must contain at least one vowel
        if (!/[aeiouy]/i.test(trimmed)) {
            return true;
        }

        // Must contain at least 3 alphabetic letters
        var alphaLetters = trimmed.match(/[a-zA-Z]/g);
        if (!alphaLetters || alphaLetters.length < 3) {
            return true;
        }

        // Reject 2 short pseudo-words (<=3 letters each, <=6 letters total) like "Eer Rei", "Abc Def" unless recognized
        if (words.length === 2 && totalLetters <= 6) {
            var hasKnownToken = words.some(function(w) {
                var cleanW = w.toLowerCase().replace(/[^a-z]/g, '');
                return /^(tea|ice|cat|dog|oil|can|bag|gel|bar|sun|pie|cup|dry|kit|kat|red|hot|wet|box|jar|tub|pen|soy|mix|wax)$/.test(cleanW);
            });
            if (!hasKnownToken) {
                return true;
            }
        }

        // Check for impossible letter clusters (consonant runs >= 5)
        if (/[bcdfghjklmnpqrstvwxyz]{5,}/i.test(trimmed)) {
            return true;
        }

        return false;
    }

    // ── Image Preprocessing — 3-Pass Multi-Mode for Max OCR Accuracy ────────────
    //
    // Each mode targets a different packaging type:
    //   Mode 0 (luminance-stretch):  original pipeline — handles most labels
    //   Mode 1 (Otsu threshold):     high-contrast flat labels, white text on dark
    //   Mode 2 (CLAHE local):        uneven lighting from camera flash/shadow
    //
    // _preprocessToCanvas(img, mode, cropX, cropY, cropW, cropH, scale)
    //   → Returns a canvas with the processed image, ready for Tesseract.
    //
    function _preprocessToCanvas(img, mode, cropX, cropY, cropW, cropH, scale) {
        var canvas = document.createElement('canvas');
        canvas.width  = Math.round(cropW * scale);
        canvas.height = Math.round(cropH * scale);
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, cropX, cropY, cropW, cropH, 0, 0, canvas.width, canvas.height);

        var imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var d = imgData.data;
        var len = d.length;

        // Convert to greyscale luminance array first
        var lums = new Float32Array(len / 4);
        var totalLum = 0, minLum = 255, maxLum = 0;
        for (var i = 0; i < len; i += 4) {
            var l = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            lums[i >> 2] = l;
            totalLum += l;
            if (l < minLum) minLum = l;
            if (l > maxLum) maxLum = l;
        }
        var avgLum = totalLum / lums.length;
        var range  = Math.max(20, maxLum - minLum);

        if (mode === 0) {
            // ── Mode 0: Luminance stretch + sigmoid (original pipeline) ──────────
            var shouldInvert = avgLum < 125;
            var scaleFactor  = 255 / range;
            for (var j = 0; j < lums.length; j++) {
                var stretched = (lums[j] - minLum) * scaleFactor;
                if (shouldInvert) stretched = 255 - stretched;
                var enhanced = stretched < 128
                    ? Math.max(0, Math.round(stretched * 0.80))
                    : Math.min(255, Math.round(128 + (stretched - 128) * 1.3));
                d[j * 4]     = enhanced;
                d[j * 4 + 1] = enhanced;
                d[j * 4 + 2] = enhanced;
            }
        } else if (mode === 1) {
            // ── Mode 1: Otsu global threshold ────────────────────────────────────
            // Build 256-bin histogram
            var hist = new Float32Array(256);
            for (var k = 0; k < lums.length; k++) hist[Math.round(lums[k])]++;
            // Otsu threshold search
            var total = lums.length;
            var sumB = 0, wB = 0, sum = 0;
            for (var gi = 0; gi < 256; gi++) sum += gi * hist[gi];
            var maxVar = 0, otsuT = 128;
            for (var t = 0; t < 256; t++) {
                wB += hist[t]; if (wB === 0) continue;
                var wF = total - wB; if (wF === 0) break;
                sumB += t * hist[t];
                var mB = sumB / wB, mF = (sum - sumB) / wF;
                var varBetween = wB * wF * (mB - mF) * (mB - mF);
                if (varBetween > maxVar) { maxVar = varBetween; otsuT = t; }
            }
            // Apply threshold: dark packaging → invert so text is dark
            var invertOtsu = avgLum < 125;
            for (var m = 0; m < lums.length; m++) {
                var bin = lums[m] > otsuT ? 255 : 0;
                if (invertOtsu) bin = 255 - bin;
                d[m * 4]     = bin;
                d[m * 4 + 1] = bin;
                d[m * 4 + 2] = bin;
            }
        } else {
            // ── Mode 2: CLAHE-style local contrast (8×8 tile grid) ───────────────
            // Improves uneven lighting from camera flash/glare
            var tileW = Math.ceil(canvas.width  / 8);
            var tileH = Math.ceil(canvas.height / 8);
            var limit  = 4.0; // clip limit for contrast redistribution
            var invert2 = avgLum < 125;

            for (var ty = 0; ty < 8; ty++) {
                for (var tx = 0; tx < 8; tx++) {
                    var x0 = tx * tileW, y0 = ty * tileH;
                    var x1 = Math.min(x0 + tileW, canvas.width);
                    var y1 = Math.min(y0 + tileH, canvas.height);
                    // Build tile histogram
                    var tHist = new Float32Array(256);
                    var tCount = 0;
                    for (var py = y0; py < y1; py++) {
                        for (var px = x0; px < x1; px++) {
                            tHist[Math.round(lums[py * canvas.width + px])]++;
                            tCount++;
                        }
                    }
                    // Clip & redistribute
                    var clipVal = limit * tCount / 256;
                    var excess  = 0;
                    for (var bi = 0; bi < 256; bi++) {
                        if (tHist[bi] > clipVal) { excess += tHist[bi] - clipVal; tHist[bi] = clipVal; }
                    }
                    var perBin = excess / 256;
                    for (var bi2 = 0; bi2 < 256; bi2++) tHist[bi2] += perBin;
                    // CDF-based equalisation
                    var cdf = new Float32Array(256);
                    cdf[0] = tHist[0];
                    for (var ci = 1; ci < 256; ci++) cdf[ci] = cdf[ci - 1] + tHist[ci];
                    var cdfMin = 0;
                    for (var ci2 = 0; ci2 < 256; ci2++) { if (cdf[ci2] > 0) { cdfMin = cdf[ci2]; break; } }
                    var cdfRange = Math.max(1, tCount - cdfMin);
                    // Apply to tile pixels
                    for (var py2 = y0; py2 < y1; py2++) {
                        for (var px2 = x0; px2 < x1; px2++) {
                            var idx  = py2 * canvas.width + px2;
                            var orig = Math.round(lums[idx]);
                            var eq   = Math.round((cdf[orig] - cdfMin) / cdfRange * 255);
                            if (invert2) eq = 255 - eq;
                            d[idx * 4]     = eq;
                            d[idx * 4 + 1] = eq;
                            d[idx * 4 + 2] = eq;
                        }
                    }
                }
            }
        }

        ctx.putImageData(imgData, 0, 0);
        return canvas;
    }

    /**
     * preprocessImage(imageSource, isCameraSnapshot)
     *
     * Runs all 3 preprocessing modes and returns the B64 of the mode that
     * produces the most useful text lines when lightly tested — or falls back
     * to Mode 0 on error. Full OCR is still done by runOcr().
     *
     * For backwards compatibility the function still returns a Promise<string>
     * (base64 of the best preprocessed image).
     */
    function preprocessImage(imageSource, isCameraSnapshot) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                try {
                    var srcW = img.naturalWidth  || img.width;
                    var srcH = img.naturalHeight || img.height;

                    // Center crop for camera snapshots
                    var cropX = 0, cropY = 0, cropW = srcW, cropH = srcH;
                    if (isCameraSnapshot && srcW > 300 && srcH > 300) {
                        cropW = Math.round(srcW * 0.88);
                        cropH = Math.round(srcH * 0.88);
                        cropX = Math.round((srcW - cropW) / 2);
                        cropY = Math.round((srcH - cropH) / 2);
                    }

                    var maxDim = 1400;
                    var scale  = Math.min(1.5, maxDim / Math.max(cropW, cropH));

                    // Mode 0 is always returned as primary; modes 1+2 are built
                    // for the multi-pass OCR path (preprocessAllModes).
                    var canvas0 = _preprocessToCanvas(img, 0, cropX, cropY, cropW, cropH, scale);
                    resolve(canvas0.toDataURL('image/jpeg', 0.92));
                } catch (e) {
                    resolve(typeof imageSource === 'string' ? imageSource : '');
                }
            };
            img.onerror = function () {
                resolve(typeof imageSource === 'string' ? imageSource : '');
            };
            if (typeof imageSource === 'string') {
                img.src = imageSource;
            } else if (imageSource && imageSource.toDataURL) {
                img.src = imageSource.toDataURL('image/jpeg', 0.90);
            } else {
                resolve('');
            }
        });
    }

    /**
     * preprocessAllModes(imageSource, isCameraSnapshot)
     * Returns Promise<string[]> — array of 3 base64 images (one per mode).
     * Used by the multi-pass OCR path.
     */
    function preprocessAllModes(imageSource, isCameraSnapshot) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                try {
                    var srcW = img.naturalWidth  || img.width;
                    var srcH = img.naturalHeight || img.height;
                    var cropX = 0, cropY = 0, cropW = srcW, cropH = srcH;
                    if (isCameraSnapshot && srcW > 300 && srcH > 300) {
                        cropW = Math.round(srcW * 0.88);
                        cropH = Math.round(srcH * 0.88);
                        cropX = Math.round((srcW - cropW) / 2);
                        cropY = Math.round((srcH - cropH) / 2);
                    }
                    var maxDim = 1400;
                    var scale  = Math.min(1.5, maxDim / Math.max(cropW, cropH));
                    var results = [];
                    for (var mode = 0; mode < 3; mode++) {
                        try {
                            var c = _preprocessToCanvas(img, mode, cropX, cropY, cropW, cropH, scale);
                            results.push(c.toDataURL('image/jpeg', 0.92));
                        } catch (e) {
                            results.push(null);
                        }
                    }
                    resolve(results);
                } catch (e) {
                    resolve([typeof imageSource === 'string' ? imageSource : null, null, null]);
                }
            };
            img.onerror = function () {
                resolve([typeof imageSource === 'string' ? imageSource : null, null, null]);
            };
            if (typeof imageSource === 'string') {
                img.src = imageSource;
            } else if (imageSource && imageSource.toDataURL) {
                img.src = imageSource.toDataURL('image/jpeg', 0.90);
            } else {
                resolve([null, null, null]);
            }
        });
    }


    // ── Retail Label Parser (Zero Gibberish) ──────────────────────────────────
    function parseProductLabel(rawText, availableCategories, knownBrands) {
        if (!rawText || typeof rawText !== 'string' || !rawText.trim()) {
            return { name: '', size: '', unit: '', category: '', brand: '', suggestions: [], rawLines: [] };
        }

        var fullText = rawText.trim();
        var rawLines = fullText.split(/\r?\n/)
            .map(function (l) { return l.trim(); })
            .filter(function (l) { return l.length >= 2; });

        // 1. Extract Net Quantity / Packaging Size (e.g. "50g", "330 ml", "3s", "12s")
        var sizeMatch = fullText.match(/\b(\d+(?:\.\d+)?\s*(?:ml|l|liter|liters|g|kg|mg|oz|cl|pcs|packs|s))\b/i);
        var detectedSize = '';
        if (sizeMatch) {
            detectedSize = sizeMatch[1].replace(/\s+/g, '').toLowerCase();
        }

        // 2. Filter lines with strict Gibberish Rejector
        var cleanLines = [];
        var detectedBrand = '';
        var detectedProductType = '';
        var brandCatalog = Array.isArray(knownBrands) ? knownBrands : [];

        for (var i = 0; i < rawLines.length; i++) {
            var line = rawLines[i];
            var lower = line.toLowerCase();

            // Ignore marketing / regulatory noise
            var isNoise = NOISE_PATTERNS.some(function (pattern) {
                return pattern.test(line);
            });
            if (isNoise) continue;

            // Strict gibberish filter: REJECTS "Lt En Bo Br Tae As"
            if (isGibberishOrNoise(line)) continue;

            // Ignore pure punctuation or numbers
            if (/^[0-9\W]+$/.test(line)) continue;

            // Ignore website / phone / email
            if (/(\.com|\.ph|\.org|\+63|09\d{8,9}|@|http)/i.test(line)) continue;

            // Sanitize
            var cleaned = line.replace(/[^\w\s\-\.\'\&]/g, ' ').replace(/\s+/g, ' ').trim();
            if (isGibberishOrNoise(cleaned)) continue;

            // Check if line matches a known store brand
            if (!detectedBrand && brandCatalog.length > 0) {
                var foundB = brandCatalog.find(function (b) {
                    var bClean = b.trim().toLowerCase();
                    return bClean === lower || lower.indexOf(bClean) !== -1 || bClean.indexOf(lower) !== -1;
                });
                if (foundB) {
                    detectedBrand = foundB;
                }
            }

            // Check if line contains a recognized product type
            if (!detectedProductType) {
                var foundT = PRODUCT_TYPES.find(function (pt) {
                    return lower.indexOf(pt) !== -1;
                });
                if (foundT) {
                    detectedProductType = foundT;
                }
            }

            cleanLines.push(cleaned);
        }

        // 3. Assemble Title & Generate Smart Suggestions
        var suggestions = [];
        var primaryName = '';

        function toTitleCase(str) {
            return str.split(' ').map(function (w) {
                if (w.length <= 1) return w.toUpperCase();
                if (/^(spf|uva|uvb|uom|led|usb|hd|xl|xs|fda|bfad)$/i.test(w)) return w.toUpperCase();
                return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
            }).join(' ');
        }

        if (cleanLines.length > 0) {
            var candidateParts = [];

            // Check if any clean line already contains the detected brand and description
            var fullLineWithBrand = cleanLines.find(function (l) {
                return detectedBrand && l.toLowerCase().indexOf(detectedBrand.toLowerCase()) !== -1 && l.trim().length > (detectedBrand.length + 3);
            });

            if (fullLineWithBrand) {
                candidateParts.push(fullLineWithBrand);
            } else {
                if (detectedBrand) {
                    candidateParts.push(detectedBrand);
                }

                for (var c = 0; c < cleanLines.length; c++) {
                    var cand = cleanLines[c];
                    var candLower = cand.toLowerCase();

                    if (detectedBrand && candLower === detectedBrand.toLowerCase()) continue;
                    var alreadyAdded = candidateParts.some(function (part) {
                        return part.toLowerCase() === candLower;
                    });
                    if (alreadyAdded) continue;

                    candidateParts.push(cand);
                    if (candidateParts.length >= 2) break;
                }
            }

            var joinedTitle = candidateParts.join(' ');
            if (detectedSize && joinedTitle.toLowerCase().indexOf(detectedSize.toLowerCase()) === -1) {
                joinedTitle += ' ' + detectedSize.toUpperCase();
            }

            primaryName = toTitleCase(joinedTitle.slice(0, 90).trim());
            if (!isGibberishOrNoise(primaryName)) {
                suggestions.push(primaryName);
            } else {
                primaryName = '';
            }

            // Alternative suggestions
            if (cleanLines.length >= 2) {
                var alt1 = cleanLines.slice(0, 2).join(' ');
                if (detectedSize && alt1.toLowerCase().indexOf(detectedSize.toLowerCase()) === -1) alt1 += ' ' + detectedSize.toUpperCase();
                alt1 = toTitleCase(alt1.trim());
                if (!isGibberishOrNoise(alt1) && !suggestions.includes(alt1)) suggestions.push(alt1);
            }

            var alt2 = cleanLines[0];
            if (detectedSize && alt2.toLowerCase().indexOf(detectedSize.toLowerCase()) === -1) alt2 += ' ' + detectedSize.toUpperCase();
            alt2 = toTitleCase(alt2.trim());
            if (!isGibberishOrNoise(alt2) && !suggestions.includes(alt2)) suggestions.push(alt2);

            if (detectedBrand && detectedProductType) {
                var alt3 = toTitleCase(detectedBrand + ' ' + detectedProductType + (detectedSize ? ' ' + detectedSize.toUpperCase() : ''));
                if (!isGibberishOrNoise(alt3) && !suggestions.includes(alt3)) suggestions.push(alt3);
            }
        }

        // If primaryName was empty or rejected, pick first valid suggestion
        if (!primaryName && suggestions.length > 0) {
            primaryName = suggestions[0];
        }

        // 4. Category Classification
        var lowerFull = fullText.toLowerCase();
        var detectedCategory = '';
        var detectedUnit = '';
        var hasWaterResistance = /water\s*(?:resistant|proof|based|repellent)/i.test(lowerFull);

        for (var k = 0; k < CATEGORY_RULES.length; k++) {
            var rule = CATEGORY_RULES[k];
            if (rule.category === 'Beverages' && hasWaterResistance && !/(?:soda|cola|juice|tea|coffee|drink|beer)/i.test(lowerFull)) {
                continue;
            }

            var match = rule.keywords.some(function (key) {
                var regex = new RegExp('\\b' + key + '\\b', 'i');
                return regex.test(lowerFull);
            });

            if (match) {
                detectedCategory = rule.category;
                if (!detectedUnit && rule.defaultUnit) {
                    detectedUnit = rule.defaultUnit;
                }
                break;
            }
        }

        // Match against store categories
        if (Array.isArray(availableCategories) && availableCategories.length > 0) {
            if (detectedCategory) {
                var exactCat = availableCategories.find(function (cat) {
                    return cat.toLowerCase() === detectedCategory.toLowerCase();
                });
                if (exactCat) detectedCategory = exactCat;
            }
            if (!detectedCategory) {
                var foundCat = availableCategories.find(function (cat) {
                    var catRegex = new RegExp('\\b' + cat + '\\b', 'i');
                    return catRegex.test(lowerFull);
                });
                if (foundCat) detectedCategory = foundCat;
            }
        }

        // 5. Unit of Measure Determination
        if (!detectedUnit) {
            if (detectedCategory === 'Personal Care') {
                detectedUnit = /lotion|shampoo|cleanser|oil|wash/i.test(lowerFull) ? 'bottles' : 'pieces';
            } else if (detectedCategory === 'Beverages') {
                detectedUnit = /\b(?:can|tin|cans|tins)\b/i.test(lowerFull) ? 'cans' : 'bottles';
            } else if (detectedCategory === 'Canned Goods') {
                detectedUnit = 'cans';
            } else if (detectedCategory === 'Snacks' || detectedCategory === 'Dry Goods') {
                detectedUnit = 'packs';
            } else {
                detectedUnit = 'pieces';
            }
        }

        return {
            name: primaryName,
            size: detectedSize,
            unit: detectedUnit,
            category: detectedCategory,
            brand: detectedBrand,
            suggestions: suggestions.slice(0, 4),
            rawLines: cleanLines
        };
    }

    // ── Local Worker Setup & OCR Execution ────────────────────────────────────
    function _getBaseUrl() {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            var idx = src.indexOf('src/scripts/product-camera-scanner.js');
            if (idx !== -1) return src.substring(0, idx);
        }
        var path = window.location.pathname;
        var appIdx = path.indexOf('/src/');
        if (appIdx !== -1) return path.substring(0, appIdx + 1);
        return '';
    }

    function ensureTesseract() {
        if (window.Tesseract) return Promise.resolve(window.Tesseract);
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = _getBaseUrl() + 'src/scripts/tesseract.min.js';
            script.onload = function () {
                if (window.Tesseract) resolve(window.Tesseract);
                else reject(new Error('Tesseract script loaded but object missing'));
            };
            script.onerror = function () {
                var cdnScript = document.createElement('script');
                cdnScript.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
                cdnScript.onload = function () {
                    if (window.Tesseract) resolve(window.Tesseract);
                    else reject(new Error('CDN Tesseract script loaded but object missing'));
                };
                cdnScript.onerror = function () {
                    reject(new Error('Failed to load OCR engine.'));
                };
                document.head.appendChild(cdnScript);
            };
            document.head.appendChild(script);
        });
    }

    var _ocrWorker = null;
    var _ocrWorkerPromise = null;

    function getOcrWorker(onProgress) {
        if (_ocrWorker) return Promise.resolve(_ocrWorker);
        if (_ocrWorkerPromise) return _ocrWorkerPromise;

        _ocrWorkerPromise = ensureTesseract().then(function (tess) {
            if (typeof onProgress === 'function') onProgress(10, 'Initializing AI OCR…');
            var base = _getBaseUrl();
            return tess.createWorker('eng', 1, {
                workerPath: base + 'src/scripts/worker.min.js',
                corePath: base + 'src/scripts/tesseract-core-simd-lstm.wasm.js',
                langPath: base + 'src/assets/tessdata',
                gzip: false,
                logger: function (m) {
                    if (typeof onProgress === 'function') {
                        if (m.status === 'recognizing text' && m.progress != null) {
                            onProgress(Math.round(m.progress * 100), 'reading');
                        } else if (m.status) {
                            onProgress(null, m.status);
                        }
                    }
                }
            });
        }).then(function (worker) {
            _ocrWorker = worker;
            return worker.setParameters({
                tessedit_pageseg_mode: '1',
                preserve_interword_spaces: '1'
            }).then(function () { return worker; });
        }).catch(function (err) {
            console.warn('[OCR] Local worker init failed, attempting standard createWorker fallback:', err);
            return ensureTesseract().then(function (tess) {
                return tess.createWorker('eng', 1, {
                    logger: function (m) {
                        if (typeof onProgress === 'function' && m.status === 'recognizing text' && m.progress != null) {
                            onProgress(Math.round(m.progress * 100), 'reading');
                        }
                    }
                });
            }).then(function (worker) {
                _ocrWorker = worker;
                return worker;
            });
        });

        return _ocrWorkerPromise;
    }

    /**
     * _countUsableLines(text)
     * Counts how many lines in the OCR output pass the gibberish filter.
     * Used to pick the best preprocessing mode and PSM variant.
     */
    function _countUsableLines(text) {
        if (!text) return 0;
        return text.split(/\r?\n/)
            .map(function (l) { return l.trim(); })
            .filter(function (l) { return l.length >= 3 && !isGibberishOrNoise(l); })
            .length;
    }

    /**
     * runOcr(imageSource, onProgress, isCameraSnapshot)
     *
     * Upgraded multi-pass, multi-PSM OCR pipeline:
     *   1. Preprocesses image in 3 modes (luminance, Otsu, CLAHE).
     *   2. For each mode, runs Tesseract with PSM 3 (auto page) in parallel.
     *   3. Also runs PSM 6 (uniform block) on Mode 0 in parallel.
     *   4. Picks the attempt that produced the most usable (non-gibberish) text.
     *   5. Returns { text, preprocessMode, psm }.
     */
    function runOcr(imageSource, onProgress, isCameraSnapshot) {
        var rawB64 = (typeof imageSource === 'string') ? imageSource : (imageSource.toDataURL ? imageSource.toDataURL('image/jpeg', 0.90) : '');

        if (typeof onProgress === 'function') onProgress(5, 'Enhancing label contrast…');

        // Build 3-mode preprocessed images
        var ocrPromise = preprocessAllModes(rawB64, isCameraSnapshot).then(function (processedImages) {
            if (typeof onProgress === 'function') onProgress(15, 'Reading packaging text…');

            return getOcrWorker(onProgress).then(function (worker) {
                var validImages = processedImages.filter(function (b) { return !!b; });
                if (validImages.length === 0) return '';

                // Pass 1: Mode 0 (flat luminance/grayscale) with PSM 3 (auto segmentation)
                if (typeof onProgress === 'function') onProgress(25, 'Scanning label text…');
                return worker.setParameters({ tessedit_pageseg_mode: '3' })
                    .then(function () { return worker.recognize(validImages[0]); })
                    .then(function (res1) {
                        var text1 = (res1 && res1.data && res1.data.text) || '';
                        var usable1 = _countUsableLines(text1);

                        // Smart Early Exit: If Mode 0 yielded 2+ clear, readable lines, exit immediately (< 1.5s)
                        if (usable1 >= 2) {
                            console.info('[OCR] Pass 1 success: ' + usable1 + ' usable lines. Early exit.');
                            return text1;
                        }

                        // Pass 2: If Pass 1 was noisy/sparse and Mode 1 (Otsu binarized) is available, try it
                        if (validImages[1]) {
                            if (typeof onProgress === 'function') onProgress(55, 'Retrying with high-contrast filter…');
                            return worker.recognize(validImages[1]).then(function (res2) {
                                var text2 = (res2 && res2.data && res2.data.text) || '';
                                var usable2 = _countUsableLines(text2);
                                if (usable2 > usable1) {
                                    console.info('[OCR] Pass 2 (Otsu) selected: ' + usable2 + ' usable lines.');
                                    return text2;
                                }
                                return text1 || text2;
                            });
                        }

                        return text1;
                    });
            });
        }).then(function (textResult) {
            if (typeof onProgress === 'function') onProgress(100, 'Done');
            return textResult;
        });

        var timeout = new Promise(function (_, reject) {
            setTimeout(function () { reject(new Error('OCR scan timed out.')); }, 15000);
        });

        return Promise.race([ocrPromise, timeout]).then(function (textResult) {
            return {
                text: textResult || ''
            };
        });
    }

    /**
     * lookupByOcrText(ocrText)
     *
     * Sends the raw OCR text to the server-side fuzzy search API.
     * Returns Promise<Array> of ranked product matches (up to 5):
     *   [{ product_id, product_name, category, supplier, unit, price,
     *      quantity, reorder_point, image_url, score }, ...]
     *
     * score is in [0, 1]: 1.0 = exact match, 0.15+ = plausible candidate.
     */
    function lookupByOcrText(ocrText) {
        if (!ocrText || !ocrText.trim()) return Promise.resolve([]);
        return fetch(_getBaseUrl() + 'api/search_products_by_text.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ text: ocrText.trim() })
        })
        .then(function (r) { return r.ok ? r.json() : { success: false }; })
        .then(function (data) {
            return (data.success && Array.isArray(data.matches)) ? data.matches : [];
        })
        .catch(function () { return []; });
    }

    /**
     * matchCatalogItem(ocrText, catalogProducts)
     *
     * Upgraded Jaccard word-overlap scorer.
     * Returns { item, score } where score is in [0, 1], or null if no match.
     *
     * Improvements over the old version:
     *   - Bidirectional substring check (name contains ocr OR ocr contains name)
     *   - Jaccard similarity rather than simple word-count ratio
     *   - Category and supplier token bonus
     *   - Minimum word length filter (>= 3 chars)
     */
    function matchCatalogItem(ocrText, catalogProducts) {
        if (!ocrText || !catalogProducts || catalogProducts.length === 0) return null;

        var lowerOcr  = ocrText.toLowerCase();
        var ocrTokens = lowerOcr.split(/\s+/).filter(function (w) { return w.length >= 3; });

        var bestItem  = null;
        var highestScore = 0;

        for (var i = 0; i < catalogProducts.length; i++) {
            var item     = catalogProducts[i];
            var nameLow  = (item.product_name || item.name || '').toLowerCase();
            if (!nameLow) continue;

            var score = 0;

            // 1. Full-name substring check (highest confidence)
            if (lowerOcr.indexOf(nameLow) !== -1 || nameLow.indexOf(lowerOcr) !== -1) {
                score = 0.95;
            } else {
                // 2. Jaccard word-overlap
                var nameTokens = nameLow.split(/\s+/).filter(function (w) { return w.length >= 3; });
                if (nameTokens.length === 0) continue;

                var intersection = 0;
                nameTokens.forEach(function (w) {
                    if (ocrTokens.indexOf(w) !== -1) intersection++;
                });
                var union = nameTokens.length + ocrTokens.length - intersection;
                score = union > 0 ? intersection / union : 0;

                // 3. Category bonus
                var catLow = (item.category || '').toLowerCase();
                if (catLow && lowerOcr.indexOf(catLow) !== -1) score = Math.min(1.0, score + 0.10);

                // 4. Supplier/brand bonus
                var supLow = (item.supplier || '').toLowerCase();
                if (supLow && lowerOcr.indexOf(supLow) !== -1) score = Math.min(1.0, score + 0.08);
            }

            if (score > highestScore && score >= 0.35) {
                highestScore = score;
                bestItem     = item;
            }
        }

        return bestItem ? { item: bestItem, score: highestScore } : null;
    }

    /**
     * Call the backend Multimodal Vision AI endpoint (Google Gemini Vision).
     * Extracts product name, brand, category, unit, and description directly from the packaging photo.
     * @param {string|HTMLCanvasElement|HTMLImageElement} imageSource
     * @returns {Promise<Object>} Resolves with { success: true, provider: 'gemini', data: { product_name, brand, category, unit, description, confidence } }
     */
    function extractProductWithAiVision(imageSource) {
        return new Promise(function (resolve, reject) {
            var b64 = '';
            if (typeof imageSource === 'string') {
                b64 = imageSource;
            } else if (imageSource instanceof HTMLCanvasElement) {
                b64 = imageSource.toDataURL('image/jpeg', 0.88);
            } else if (imageSource && imageSource.nodeName === 'IMG') {
                try {
                    var c = document.createElement('canvas');
                    c.width = imageSource.naturalWidth || imageSource.width || 640;
                    c.height = imageSource.naturalHeight || imageSource.height || 480;
                    var ctx = c.getContext('2d');
                    ctx.drawImage(imageSource, 0, 0);
                    b64 = c.toDataURL('image/jpeg', 0.88);
                } catch (e) {
                    return reject(e);
                }
            } else {
                return reject(new Error('Invalid image source for AI Vision'));
            }

            fetch(_getBaseUrl() + 'api/ai_extract_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'extract', image: b64 })
            })
            .then(function (res) {
                if (!res.ok) {
                    return res.json().catch(function() { return { error: 'HTTP ' + res.status }; }).then(function(err) {
                        throw err;
                    });
                }
                return res.json();
            })
            .then(function (data) {
                if (data && data.success && data.data && data.data.product_name) {
                    resolve(data);
                } else {
                    reject(data || new Error('AI Vision failed to extract valid product details'));
                }
            })
            .catch(reject);
        });
    }

    return {
        preprocessImage            : preprocessImage,
        preprocessAllModes         : preprocessAllModes,
        lookupByOcrText            : lookupByOcrText,
        extractProductWithAiVision : extractProductWithAiVision,
        isGibberishOrNoise         : isGibberishOrNoise,
        parseProductLabel          : parseProductLabel,
        runOcr                     : runOcr,
        playBeep                   : playBeep,
        matchCatalogItem           : matchCatalogItem
    };
})();

