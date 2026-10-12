<?php

/*
| Snapshot of the 36 published posts on finnskerrisdaleladies.com (taken 2026-10-08 from the public site).
| Used by ContentSeeder so the replica shows the real blog before the WordPress database is imported.
| `php artisan wp:import` later updates these rows in place (matched by wp_id, then slug).
*/

$u = fn (string $p) => 'https://finnskerrisdaleladies.com/wp-content/uploads/'.$p;
$fig = fn (string $p, int $w, int $h, string $alt = '', ?string $cap = null) => '<figure class="wp-block-image size-large"><a href="'.$u(preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $p)).'"><img src="'.$u($p).'" alt="'.htmlspecialchars($alt).'" width="'.$w.'" height="'.$h.'" loading="lazy"></a>'.($cap ? '<figcaption>'.$cap.'</figcaption>' : '').'</figure>';
$img = fn (string $p, int $w, int $h, string $alt = '') => '<a href="'.$u(preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $p)).'"><img class="alignnone" src="'.$u($p).'" alt="'.htmlspecialchars($alt).'" width="'.$w.'" height="'.$h.'" loading="lazy"></a>';
$p = fn (string $t) => '<p>'.$t.'</p>';
$cap = fn (string $p, int $w, int $h, string $c) => '<div class="wp-caption alignnone" style="width:'.($w + 10).'px">'.'<a href="'.$u(preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $p)).'"><img src="'.$u($p).'" alt="'.htmlspecialchars($c).'" width="'.$w.'" height="'.$h.'" loading="lazy"></a><p class="wp-caption-text">'.$c.'</p></div>';

return [
    [
        'wp_id' => 585, 'slug' => 'more-from-shan-collections', 'title' => 'MORE FROM SHAN COLLECTIONS', 'date' => '2024-03-19 06:52:56', 'brand' => 'shan-collections',
        'content' => $fig('2024/03/thumbnail_Whitagram-Image-34.jpg', 800, 800)
            .$p('THE SCUBA FABRIC FROM SHAN COLLECTIONS HAS MADE THEM WORLD RE-KNOWN – THIS IS THE MOST COMFORTABLE FABRIC TO WEAR IF YOU ARE LOVING A STRETCH FABRIC THAT MOVES WHEN YOU DO! HERE THEY’VE MADE THE MOST ADORABLE SKIRT – BELOW THE KNEE FOR A CHANGE! – WITH CUTOUT DETAIL THAT IS ECHOED IN THE JACKET/CARDIGAN – ALSO THE TANK TOP IS THE PERFECT UNDERPINNING TO THIS OUTFIT! THE SKIRT WITHOUT CUTOUT IS AVAILABLE AS WELL AS THEIR TROUSER – EITHER TAPERED OR BOOTCUT SILHOUETTE')
            .$fig('2024/03/thumbnail_Whitagram-Image-36.jpg', 800, 800)
            .$p('AND FOR THOSE THAT PREFER WEARING A DRESS TO A SKIRT AND TOP THEY’VE MADE THIS BELOW THE KNEE LOOK WITH A MESH SHORT SLEEVE THAT ACTUALLY IS LONG ENOUGH TO HIT THE ELBOW – AND YES, A V-NECK IS ALWAYS FLATTERING!')
            .$fig('2024/03/thumbnail_Whitagram-Image-38.jpg', 797, 797)
            .$p('FOR THOSE THAT CAN MAKE AN OUTFIT FROM SPORTSWEAR – AND SO MANY OF US CAN NOW – THIS MODERN LACE BUTTONED-DOWN SHIRT IS SO VERY CUTE! EITHER OVER THE TANK TOP AS SHOWN OR PERHAPS A SLEEVELESS DRESS OR TOP – THE POSSIBILITIES ARE ENDLESS – AND SHIRT AVAILABLE IN WHITE AS WELL!'),
    ],
    [
        'wp_id' => 578, 'slug' => 'more-lc-the-big-blue', 'title' => 'MORE LC THE BIG BLUE', 'date' => '2024-03-15 01:15:02', 'brand' => 'luisa-cerano',
        'content' => $fig('2024/03/thumbnail_IMG_6731-1-1-688x1024.jpg', 688, 1024)
            .$p('ANOTHER SILHOUETTE IN THE AMAZING COTTON/VOILE FABRIC – THIS TIME A KIMONO STYLE BLOUSE WITH 3/4 SLEEVE AS WELL AS THE FEMININE TIE AT NECKLINE – SHOWN HERE WITH THE DENIM CARGO PANT WITH POCKET DETAIL')
            .$fig('2024/03/thumbnail_IMG_6804-675x1024.jpg', 675, 1024)
            .$p('AND STRIPES SEEN EVERYWHERE – HERE IN THE COTTON CAP SLEEVE T-SHIRT AND PUNTO MILANO FULL LENGTH TROUSER WITH POCKET DETAIL – THIS IS A PULL-ON PANT – SO COMFORTABLE TO WEAR AND YET YOU LOOK SO EFFORTLESSLY STYLISH!')
            .$fig('2024/03/thumbnail_Whitagram-Image-33.jpg', 800, 800)
            .$p('AND WHAT GROUPING WOULD BE COMPLETE WITHOUT THE REQUISITE COTTON CARDIGAN – THIS TIME IN DARK NAVY – SHOWN WITH THE COTTON STRIPE HALTER UNDER AND MILKY WHITE DENIM TROUSER – SO SHARP!'),
    ],
    [
        'wp_id' => 569, 'slug' => 'luisa-cerano-the-big-blue', 'title' => 'LUISA CERANO THE BIG BLUE!', 'date' => '2024-03-14 06:20:42', 'brand' => 'luisa-cerano',
        'content' => $fig('2024/03/thumbnail_IMG_6643-1.jpg', 947, 423)
            .$p('WE STILL LOVE PASTELS BUT HOW CAN WE NOT FALL IN LOVE WITH NAVY, NAVY AND WHITE AND DENIM ALL OVER AGAIN! THE DENIM PANTSUIT IS REINVENTED HERE IN A SHORT JACKET WITH OVERSIZED POCKETS AND HIGH-WAISTED PANTS WITH CARGO AND POCKET DETAIL – THE HALTER STRIPED T-SHIRT IS THE PERFECT TOP TO OFFSET THIS STYLISH LOOK – A SPECIAL BUT AMAZING OUTFIT THAT WILL ALWAYS BE THERE FOR YOU WHEN U NEED IT!')
            .$fig('2024/03/thumbnail_IMG_6729-1.jpg', 665, 521)
            .$p('STRIPES ALWAYS ALWAYS SHOW UP NOW IN EVERY NEW SEASON – YOU JUST CAN’T GET AWAY FROM THEM NOR SHOULD YOU – AN EASY WAY TO LOOK FRESH CONTEMPORARY AND SPORTY FOR THE SEASON – ESPECIALLY SPRING! THIS STRIPED PULLOVER IS SPECIAL BECAUSE IT HAS A SPARKLE! AND PAIRED WITH A SATIN NAVY TROUSER YOU LOOK CHIC AND SPORTY BUT ELEGANT – WE DO HAVE NAVY TROUSERS IN STRETCH COTTON AND PUNTO MILANO AS WELL! YOU HAVE YOUR CHOICE!')
            .$fig('2024/03/thumbnail_Whitagram-Image-19-1.jpg', 800, 800)
            .$p('LINEN LOVERS UNITE OVER THIS STRETCH LINEN/VISCOSE BLEND BLAZER AND TROUSER! THE SOFTEST LINEN FABRIC ON THE MARKET – THIS OUTFIT FEELS LIKE A SECOND SKIN ON THE BODY! THAT ALONG WITH THE STUNNING CREAMY BUTTONS MAKES THIS A PANTSUIT TO DEFINITELY OWN FOR THIS SPRING AND SUMMER!')
            .$fig('2024/03/thumbnail_Whitagram-Image-24-1.jpg', 800, 800)
            .$p('AND YOU HAVE TO KNOW IF NAVY IS TRENDING SO IS WHITE – AND THIS TIME ITS A MILKY WHITE! SO SOPHISTICATED IN THE TAFFETA PARKA COAT – WITH DRAWSTRING DETAIL AND HOOD! THIS PARKA GOES OVER EVERYTHING IN YOUR SPRING AND SUMMER WARDROBE AND TRAVELS WITH YOU EVERYWHERE YOU GO! PAIRED WITH THE SPARKLY CHUNKY COTTON KNIT VEST AND COTTON TROUSER – YOU NOW LOOK LIKE A MILLION $$$!')
            .$fig('2024/03/thumbnail_IMG_6730-688x1024.jpg', 688, 1024)
            .$p('LUISA CERANO MAKES THE MOST BEAUTIFUL BLOUSES! CHOOSE EITHER STRIPES OR ONE OF THEIR ORIGINAL PRINTS – THIS SEASON AN AMAZING POP-ART PRINT IN BLUE TONES IN EITHER A BUTTONED DOWN BLOUSE AS SHOWN OR A KIMONO STYLE BLOUSE WITH BOW AT NECKLINE! THESE ARE COTTON/SILK VOILE FABRICS – PERFECT FOR WARM WEATHER AT HOME OR ABROAD!'),
    ],
    [
        'wp_id' => 557, 'slug' => 'riani-grouping-2-for-spring', 'title' => 'RIANI GROUPING #2 FOR SPRING!', 'date' => '2024-03-09 06:55:47', 'brand' => 'riani',
        'content' => $fig('2024/03/thumbnail_Whitagram-Image-10.jpg', 640, 640, '', 'THIS TAILORED NAVY BLAZER IS FITTED FOR PERFECTION OVER THE VISCOSE KNIT PLEATED SKIRT – SIMPLY OUTSTANDING! WHETHER YOU ARE OFF TO AN OCCASION OR DRESSING LIKE THE PROFESSIONAL YOU ARE – EITHER OPTION IS A REASON TO ADD THIS OUTFIT TO YOUR WARDROBE')
            .$fig('2024/03/thumbnail_Whitagram-Image-14.jpg', 640, 640)
            .$p('FOR THOSE OF US WHO LOVER COLOURS ON THE BRIGHTER SIDE THIS ‘LAC BLUE’ IS A TERRIFIC CHOICE! THIS WOOL BLEND INDOOR/OUTDOOR JACKET WILL BRIGHTEN UP ALL OF YOUR NAVY OR BLACK COLOURS – WE EVEN DO HAVE THE MATCHING BLOUSE TO MAKE THE PERFECT TWINSET – RIANI NEVER DISAPPOINTS WHEN IT DOES SOMETHING RIGHT !')
            .$fig('2024/03/thumbnail_Whitagram-Image-15.jpg', 650, 650)
            .$p('DENIM IS MAKING ANOTHER HUGE COMEBACK – LOL I THINK I SAY THAT EVERY SEASON BUT THIS SEASON MORE THAN EVER AND THIS TIME IT’S A DENIM JACKET WITH COORDINATING DENIM TROUSER – AND HOW NICE TO SEE THIS DRESSED UP WITH A NOVELTY TANK UNDERNEATH WITH CHIFFON BOW – DON’T WAIT TO WEAR THIS!')
            .$fig('2024/03/thumbnail_Whitagram-Image-12.jpg', 800, 800)
            .$p('AND YES NAVY ONCE AGAIN BECOMES THE GO TO COLOUR FOR THE SPRING SEASON – THEY FOUND A JAPANESE TECHNO SOFTLY BLENDED FABRIC TO MAKE THIS PANT SUIT – BEAUTIFUL SILVER CLOSURES ON THE JACKET AND A COMFORTABLE SOFTLY DRAPED TROUSER – ONCE AGAIN YOU SEE THEIR TANK TOP UNDER WITH BEAUTIFUL CHIFFON BOW BUT THIS TIME IN OFF WHITE COLOUR!'),
    ],
    [
        'wp_id' => 554, 'slug' => 'rafaello-rossi-spring-24-2', 'title' => 'RAFAELLO ROSSI SPRING ’24', 'date' => '2024-03-09 06:45:53', 'brand' => 'rafaello-rossi',
        'content' => $fig('2024/03/thumbnail_Whitagram-Image-26.jpg', 800, 800)
            .$p('NEW DELIVERY FROM RAFAELLO ROSSI IS ALWAYS LOADED WITH OPPORTUNITY! THE FADED DENIM IS STRONG FOR THIS NEW SEASON – SEEN HERE IN A CROPPED SILHOUETTE WITH EMBROIDERY DETAIL AT HEM AND ALSO AVAILABLE IN THE SOFT WHITE COLOUR – TAKE YOUR PICK! BUT A GREAT OPTION FOR THOSE TRAVELLING!'),
    ],
    [
        'wp_id' => 551, 'slug' => 'shan-collections-spring-24', 'title' => 'SHAN COLLECTIONS SPRING ’24', 'date' => '2024-03-09 06:42:44', 'brand' => 'shan-collections',
        'content' => $fig('2024/03/thumbnail_Whitagram-Image-25.jpg', 800, 800)
            .$p('SHAN COLLECTIONS IS KNOWN WORLD-WIDE FOR THEIR AMAZING SCUBA FABRIC WHICH LITERALLY FEELS LIKE A SECOND SKIN ON YOUR BODY – IT IS JUST THAT COMFORTABLE! WE KEEP A STEADY INVENTORY OF THEIR TROUSERS AND SKIRTS IN BLACK AND NAVY AND HAVE NOW ADDED THEIR NOVELTY BLAZER IN CUT-OUT DESIGN TO MAKE A SPRING TIME SUIT FOR THE NEW SEASON! BUT THEY ARE NOT A ONE FABRIC LABEL – EACH SEASON THEY ARE ABLE TO HUNT DOWN OUTSTANDING PRINTS AND FABRICATIONS FOR THEIR NEW COLLECTIONS. SEEN HERE IS THE COTTON BLEND BOMBER JACKET WITH ZIPPER IN A ONE OF A KIND FABRIC TO TAKE YOU ON YOUR NEXT HOLIDAY OR SOIREE! PLEASE COME TO CHECK OUT THIS VERSATILE AND PRACTICAL YET OUTSTANDING COLLECTION TODAY!'),
    ],
    [
        'wp_id' => 529, 'slug' => 'pastel-ease-luisa-cerano', 'title' => 'PASTEL EASE LUISA CERANO', 'date' => '2024-02-02 06:05:16', 'brand' => 'luisa-cerano',
        'content' => $fig('2024/02/thumbnail_Whitagram-Image-8.jpg', 800, 800)
            .$p('WONDERFUL TO SEE NATURAL LEATHERS BEING SHOWN AGAIN – PLEATHER HAS BEEN WONDERFUL YES! BUT AN INVESTMENT IN A SUEDE JACKET KEEPS ITS VALUE FOR YEARS THIS CASHEW COLOURED SHIRT JACKET IS SO VERY NICE – SHOWN WITH A FADED DENIM TROUSER AND EXQUISITE PRINTED COTTON SHIRT – TERRIFIC OUTFIT!')
            .$fig('2024/02/thumbnail_Whitagram-Image-7.jpg', 640, 640)
            .$p('THE PASTEL COLOURS ARE ALWAYS WELCOME FOR THE START OF A WARMER SEASON – SEEN HERE IS THE PALE CITRON – A SIMPLY DELICIOUS VERSION OF YELLOW THAT LOOKS GOOD ON MOST SKIN TONES THE BOOTCUT DENIM CUT TROUSER LUREX BLOUSE AND PUFFY JACKET ALL WORK TO EASE US INTO A NEW SEASON! SIMPLY OUTSTANDING!')
            .$fig('2024/02/thumbnail_Whitagram-Image-4.jpg', 640, 640)
            .$p('AND YES THE DENIM JACKET IS BACK BABY – SHORTER AND FADED WITH THREE-QUARTER SLEEVES – MEANT TO MIX AND MATCH OVER MOST ITEMS IN YOUR WARDROBE AND EVEN TO WEAR WITH ALL YOUR DENIM SO CUTE!')
            .$fig('2024/02/thumbnail_IMG_5920.jpg', 672, 514)
            .$p('CONTINUING WITH THE PASTEL THEM IS THE MINT COLOUR – THE SOFTEST VERSION OF GREEN – IN A LIGHTWEIGHT COTTON PULLOVER AND COTTON BLEND TROUSER')
            .$fig('2024/02/thumbnail_IMG_5922.jpg', 681, 521)
            .$p('MIX AND MATCH IS YOUR OPTION HERE AS WELL – THE MINT COLOURED TROUSER SHOWN WITH A PALE CITRON COTTON DRAWSTRING SHIRT – STAND OUT IN A CROWD – WEAR PASTEL!'),
    ],
    [
        'wp_id' => 523, 'slug' => 'cambio-24', 'title' => 'CAMBIO ’24', 'date' => '2024-01-26 02:35:23', 'brand' => 'cambio',
        'content' => $fig('2024/01/thumbnail_IMG_5844-685x1024.jpg', 685, 1024)
            .$p('THE CAMBIO TROUSERS ARE SO AWESOME! SEEN HERE IN A WIDE STRETCH WAISTBAND CROPPED STRAIGHT LEG')
            .$fig('2024/01/thumbnail_IMG_5846-688x1024.jpg', 688, 1024)
            .$p('HERE IS THE PUNTO MILANO FABRIC IN WILD ORCHID – FITTED AND FLATTERING')
            .$fig('2024/01/146325-Black_8-683x1024.jpg', 683, 1024)
            .$p('AND LASTLY FOR NOW – THE TAPERED CIGARETTE LEG WITH STRETCH WAISTBAND AND TUXEDO STRIPE DOWN THE SIDE IN TONE ON TONE BLACK – THIS IS A SHARP PANT – DRESS UP OR DOWN – DYNAMITE FABRIC AND CUT!'),
    ],
    [
        'wp_id' => 515, 'slug' => 'riani-spring-2024-delivery-one', 'title' => 'RIANI SPRING 2024 DELIVERY ONE', 'date' => '2024-01-21 07:58:27', 'brand' => 'riani',
        'content' => $fig('2024/01/riani-s24-1024x1015.jpg', 1024, 1015)
            .$p('BEAUTIFUL BLACK AND WHITE! RIANI COMES OUT WITH A BANG FOR THEIR FIRST SPRING DELIVERY – VERSATILE GARMENTS TO BEGIN WEARING RIGHT AWAY AND ALL YEAR LONG')
            .$fig('2024/01/RIANI-spring-2024-783x1024.jpg', 783, 1024)
            .$p('THIS STUNNING BLACK AND WHITE STRIPED VISCOSE BLOUSE IS OVER-SIZED TO MAKE IT WEARABLE IN SO MANY DIFFERENT WAYS – TUCKED IN, WORN OUT, TIED, FRENCH TUCKED – A DYNAMITE ITEM TO BRIGHTEN YOUR BLACK!')
            .$fig('2024/01/riani-24.jpg', 800, 800)
            .$p('AND WHO DOESN’T LIKE THE JE NE SAIS QUOIS OF THE FRENCH INFLUENCED STRIPED T-SHIRT – SHOWN HERE IN A LONG SLEEVED STRETCH COTTON BLEND – MAKES AN IMPRESSION THE SECOND YOU PUT IT ON!')
            .$fig('2024/01/RIANI-SPRING-24.jpg', 800, 800)
            .$p('STRIPED T-SHIRT IS SEEN HERE UNDER THE COTTON BLEND CHANEL-STYLE CROPPED JACKET – POWER DRESSING!')
            .$fig('2024/01/RIANI-GLAM-9.jpg', 392, 636)
            .$p('AND BE AWARE THE LEATHER MOTORCYCLE JACKET IS MAKING A COME-BACK – SO EASY TO WEAR OVER SO MANY THINGS – SHOWN HERE WITH THEIR BEAUTIFUL FLORAL PRINT SKIRT – WHICH WE HAVE IN STOCK AS WELL!'),
    ],
    [
        'wp_id' => 512, 'slug' => 'rafaello-rossi-spring-24', 'title' => 'RAFAELLO ROSSI SPRING ’24', 'date' => '2024-01-21 07:42:37', 'brand' => 'rafaello-rossi',
        'content' => $fig('2024/01/ROSSI-SPRING-24.jpg', 800, 800)
            .$p('FANTASTIC POLY-JERSEY SOFT AS BUTTER STRETCH TROUSERS – A MUST FOR YOUR TRAVEL WARDROBE – CROPPED AND PEGGED IN WHITE COLOUR OR FULL LENGTH AND STRAIGHT CUT WITH DRAWSTRING WAIST DON’T DISCOUNT HOW FUN IT IS TO WEAR A VIBRANT ORANGE TROUSER – THIS MAKES YOUR OUTFIT WITH VARIOUS AND ASSORTED TOPS'),
    ],
    [
        'wp_id' => 502, 'slug' => 'spring-2021-more-luisa-cerano', 'title' => 'SPRING 2021 – MORE LUISA CERANO!', 'date' => '2024-01-21 06:06:45', 'brand' => 'luisa-cerano',
        'content' => $fig('2024/01/lc-sring-24.jpg', 800, 800)
            .$p('VISCOSE FABRIC CONTEMPORARY FLORAL PRINT SLEEVELESS DRESS – A CLASSIC CUT DRESS WHICH SHOWS THE FIGURE – INVEST IN A CLASSIC DRESS AND HAVE IT IN YOUR CLOSET EXACTLY WHEN YOU NEED IT!')
            .$fig('2024/01/lc-spring-24-699x1024.jpg', 699, 1024)
            .$p('WOOL/CASHMERE DOUBLE-FACE FABRIC IN BLACK COLOUR – NO COLLAR! SIMPLY ELEGANT WORN INDOOR/OUTDOOR – A PEA JACKET FOR VERSATILE USE THROUGH THE YEAR')
            .$fig('2024/01/thumbnail_Whitagram-Image-1.jpg', 800, 800)
            .$p('FADED LAVENDER – A BEAUTIFUL SOFT PASTEL SHADE THAT MIXES WITH BLACK SO WELL – SEEN HERE IN A COZY KNIT CARDIGAN, BLACK COTTON TROUSER AND REALISTIC CHERRY PRINT T-SHIRT')
            .$fig('2024/01/thumbnail_Whitagram-Image-2.jpg', 800, 800)
            .$p('FADED LAVENDER AGAIN IN THE WOOL/CASHMERE DOUBLE-FACE OVER-SIZED COAT – NOT LINED TO LAYER EASILY OVER YOUR KNITWEAR AND JACKETS – THE COLOUR IS YOUR REASON TO BUY HERE!')
            .$fig('2024/01/thumbnail_IMG_5281-681x1024.jpg', 681, 1024)
            .$p('KNITWEAR HAS AND WILL ALWAYS BE THE PASSION OF THE LUISA CERANO LABEL – THIS SHADOW STRIPED CARDIGAN IN THE COLOURS OF FADED LAVENDER SMOKY AQUA AND BLACK IS A KNIT COAT FOR WEARING EVEN ON THE COLDEST OF DAYS – THE MATCHING SCARF HELPS KEEP YOU WARM – AND YES IT WILL LAYER OVER TOP YOUR KNIT PULLOVERS – THIS IS A COLLECTOR’S ITEM FOR LC LOVERS!')
            .$fig('2024/01/lc-spring-24-1-663x1024.jpg', 663, 1024)
            .$p('THE KEYHOLE NECK IS MADE CONTEMPORARY NOW IN SO MANY WAYS – SEEN HERE IN A VISCOSE KNIT WE ALSO DO HAVE IT IN THE WOOL/CASHMERE BLEND – AND HOW WONDERFUL TO MIX WITH A PASTEL SHADE!')
            .$fig('2024/01/thumbnail_Whitagram-Image-3.jpg', 800, 800)
            .$p('THE UBIQUITOUS CAMI WITH RHINESTONE TRIM DRESSES US UP WITH ANY OF OUR JACKETS OR CARDIGANS ALSO AVAILABLE IN SMOKY AQUA AS WELL'),
    ],
    [
        'wp_id' => 498, 'slug' => 'spring-2024', 'title' => 'SPRING 2024', 'date' => '2024-01-19 03:56:09', 'brand' => 'luisa-cerano',
        'content' => $fig('2024/01/thumbnail_IMG_5237-758x1024.jpg', 758, 1024)
            .'<p class="has-small-font-size">NEW LUISA CERANO FOR A NEW SEASON! LEATHER IS BACK BABY AND A HAPPY RETURN FOR MANY EVEN THOUGH</p>'
            .'<p class="has-small-font-size">THE PLEATHER TREND HAS BEEN FANTASTIC! BUT LEATHER TAKES US BACK TO PURCHASING GARMENTS THAT HAVE INHERENT VALUE</p>'
            .'<p class="has-small-font-size">AS LEATHER DOES! SEEN HERE IN PENCIL SKIRT WITH ELASTIC WAISTBAND AND FRONT SLIT</p>'
            .'<p class="has-small-font-size">NOTICE JUSTAPOSITION WITH NYLON BOMBER JACKET WITH HOOD AND OUTSTANDING REALIST CHERRY PRINT T-SHIRT</p>',
    ],
    [
        'wp_id' => 393, 'slug' => 'riani-s21', 'title' => 'RIANI S’21', 'date' => '2021-04-09 01:39:42', 'brand' => 'riani',
        'content' => '<p>RIANI IS A BRAND CLOSE TO MY HEART AS WE HAVE BEEN CARRYING THIS LINE HERE AT FINNS KERRISDALE LADIES SINCE REOPENING IN 2007. THE RIANI STRENGTH WAS ORIGINALLY IN TROUSERS – THEIR FIRST FORAY INTO THE GARMENT INDUSTRY- BUT EVENTUALLY BRANCHED OUT AS SO MANY OF THEM DO INTO KNITWEAR BLAZERS DRESSES AND OUTERWEAR. THE NICE THING ABOUT RIANI IS ITS ABILITY TO APPEAL TO A BROAD SPECTRUM OF THE LADIES WEAR MARKET AS IT FOLLOWS THE TRENDS WANTING YOU TO BE UP TO THE MINUTE BUT WITH ITS PERSPECTIVE DEEPLY GROUNDED IN VERSATILITY VALUE FOR YOUR MONEY AND THE HOPE TO ENJOY YOUR ITEMS FOR MANY YEARS. I HAVE RIANI OUTFITS IN MY CLOSET THAT I CONTINUE TO TREASURE AFTER THEIR ARRIVAL SO MANY SEASONS AGO.PLEASE PERUSE THE IMAGES BELOW AS THESE OUTFITS ARE ALL REPRESENTED IN THE STORE FOR THIS SPRING/SUMMER SEASON. NEUTRALS ARE PREDOMINANT WITH BLACK AND NAVY GROUNDING THESE PALER COLOURS. EVEN I AM APPRECIATING A RETURN TO NEUTRAL TONES AFTER WEARING THE BRIGHTS FOR SO MANY YEARS. '
            .$img('2021/04/thumbnail_IMG_6310-196x300.jpg', 196, 300, 'thumbnail_IMG_6310').'<br>ON THE CHANCE YOU DO HAVE AN OCCASION TO ATTEND THIS SEASON PLEASE CONSIDER THIS RIANI DRESS IN BISCUIT COLOUR LONG SLEEVED A LINE BOW DETAIL AND JUST TO THE KNEE CUTE AS A BUTTON</p>'
            .'<p>'.$img('2021/04/thumbnail_IMG_6311-192x300.jpg', 192, 300, 'thumbnail_IMG_6311').'<br>THE SWEATSUIT TREND SPILLS OVER INTO SPORTSWEAR WITH THIS JERSEY TRENCH COAT AND PULL ON TROUSER SUPER COMFORTABLE BUT AS AN OUTFIT WILL TAKE YOU ANYWHERE</p>'
            .'<p>'.$img('2021/04/thumbnail_IMG_6312-201x300.jpg', 201, 300, 'thumbnail_IMG_6312').'<br>THE COAT IS ALWAYS THE MOST IMPORTANT PIECE IN OUR WARDROBE SHOWN HERE IN NAVY WITH DETACHABLE HOOD AND SNAP BUTTON DETAIL SO CUTE RUNNING UP AND DOWN THE SIDE THIS COAT IS A WINNER IN ITS VERSATILITY<br>LITERALLY GOES OVER ANYTHING IN ITS ROOMINESS</p>'
            .'<p>'.$img('2021/04/thumbnail_IMG_6386-176x300.jpg', 176, 300, 'thumbnail_IMG_6386').'<br>THOSE OF YOU WITH WAISTLINES TO SHOW OFF MUST GIVE THIS OUTFIT A TRY LINEN SAFARI JACKET AND HIGH WAISTED STYLISH TROUSER SUBTLE IN COLOUR BUT PACKS A PUNCH IN EFFECT</p>'
            .'<p>'.$img('2021/04/thumbnail_IMG_6387-186x300.jpg', 186, 300, 'thumbnail_IMG_6387').'<br>THE LINEN TREND FOR SUMMER CONTINUES HERE IN ANOTHER SAFARI LOOK GORGEOUS DRESS IN WARM TONE OF SOFT CAMEL BELTED OR NOT ITS UP TO YOU</p>'
            .'<p>'.$img('2021/04/thumbnail_IMG_6388-200x300.jpg', 200, 300, 'thumbnail_IMG_6388').'<br>AND FOR THOSE WHO LIKE MYSTERY A LONG VEST OVER A WIDE-LEGGED TROUSER IS IT BEST THING ABOUT THIS PHOTO IS THE SURPRISE OF A V NECK TOP AND NO SLEEVES UNDER THE SLEEVELESS VEST BE BRAVE AND BANISH THE SLEEVE!</p>',
    ],
    [
        'wp_id' => 388, 'slug' => 'spring-21-fringe-coat-from-luisa-cerano', 'title' => 'Spring ’21 Fringe Coat from Luisa Cerano', 'date' => '2021-03-31 06:05:43', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2021/03/thumbnail_IMG_6621-300x279.jpg', 300, 279, 'thumbnail_IMG_6621').$img('2021/03/thumbnail_IMG_6622-204x300.jpg', 204, 300, 'thumbnail_IMG_6622').$img('2021/03/thumbnail_IMG_6623-300x300.jpg', 300, 300, 'thumbnail_IMG_6623').'</p>'
            .$p('Wool blend fabric in light grey colour with fringe detail on sleeve – wear belted or not – your choice! The ultimate in coat dressing from Luisa Cerano for Spring 2021!'),
    ],
    [
        'wp_id' => 381, 'slug' => 'shan-collections-s21', 'title' => 'SHAN COLLECTIONS S’21', 'date' => '2021-03-31 05:19:23', 'brand' => 'shan-collections',
        'content' => '<p>'.$img('2021/03/thumbnail_IMG_6412-243x300.jpg', 243, 300, 'thumbnail_IMG_6412').$img('2021/03/thumbnail_IMG_5757-244x300.jpg', 244, 300, 'thumbnail_IMG_5757').$img('2021/03/thumbnail_IMG_6615-200x300.jpg', 200, 300, 'thumbnail_IMG_6615').'</p>'
            .$p('SHAN COLLECTIONS is a high end resort line of clothing sold all over the world and originating in Montreal Canada. Their fabrics come from Italy. Photos above show the latest delivery for Spring ’21 – vegan leather accents on scuba like fabrications in colours of silver black and pink. A most glamorous way to dress down! Trousers are pull on for ultimate comfort, zip jackets, stretch turtlenecks and two piece lounging outfits are all part of the collection.'),
    ],
    [
        'wp_id' => 373, 'slug' => 'restart-group-3-lc-s21-2', 'title' => 'Restart Group #3 Luisa Cerano S’21', 'date' => '2021-03-31 04:51:43', 'brand' => 'luisa-cerano',
        'content' => $cap('2021/03/thumbnail_IMG_6555-300x298.jpg', 300, 298, 'Denim short trenchcoat style jacket in sage colour')
            .'<p>'.$img('2021/03/thumbnail_IMG_6556-300x295.jpg', 300, 295, 'thumbnail_IMG_6556').'</p>'
            .$p('Soft green hooded vest with snap detail for poncho effect')
            .'<p>'.$img('2021/03/thumbnail_IMG_6552-300x297.jpg', 300, 297, 'thumbnail_IMG_6552').$img('2021/03/thumbnail_IMG_6562-215x300.jpg', 215, 300, 'thumbnail_IMG_6562').'</p>'
            .$p('Cotton blend raspberry pullover with cotton striped blouse')
            .'<p>'.$img('2021/03/thumbnail_IMG_6558-220x300.jpg', 220, 300, 'thumbnail_IMG_6558').'</p>'
            .$p('Cotton floral print buttoned-down dress with ruffle detail')
            .'<p>'.$img('2021/03/thumbnail_IMG_6561-211x300.jpg', 211, 300, 'thumbnail_IMG_6561').'</p>'
            .$p('Gorgeous multi-coloured cotton pullover'),
    ],
    [
        'wp_id' => 366, 'slug' => 'luisa-cerano-embracing-nature-s21', 'title' => 'LUISA CERANO EMBRACING NATURE S’21', 'date' => '2021-03-31 04:43:17', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2021/03/thumbnail_IMG_5747-300x300.jpg', 300, 300, 'thumbnail_IMG_5747').'</p>'
            .$p('Mohair blend pullover over cotton blend long skirt')
            .$cap('2021/03/thumbnail_IMG_5750-300x298.jpg', 300, 298, 'Cotton blend pullover over chiffon print blouse and high-waisted chalk coloured denim trouser')
            .'<p>'.$img('2021/03/thumbnail_IMG_6213-300x300.jpg', 300, 300, 'thumbnail_IMG_6213').'</p>'
            .$p('Poly blend dress in bambi colour with tie belt detail')
            .'<p>'.$img('2021/03/thumbnail_IMG_6214-202x300.jpg', 202, 300, 'thumbnail_IMG_6214').'</p>'
            .$p('Wool blend cardigan with fringe detail vegal leather trouser and t shirt with heart motif'),
    ],
    [
        'wp_id' => 343, 'slug' => 'spring-2021-new-arrivals', 'title' => 'SPRING 2021 NEW ARRIVALS', 'date' => '2021-03-31 04:20:13', 'brand' => 'luisa-cerano',
        'content' => $cap('2021/03/thumbnail_file-2-203x300.jpg', 203, 300, 'LUISA CERANO SPRING 2021 Restart grouping')
            .$p('Dark navy gauze skirt with cotton blend blazer')
            .'<p>'.$img('2021/03/thumbnail_file-1-204x300.jpg', 204, 300, 'thumbnail_file (1)').'</p>'
            .$p('LUISA CERANO Restart').$p('Dusty pink mohair blend pullover with vegan leather trouser'),
    ],
    [
        'wp_id' => 332, 'slug' => '332-2', 'title' => '', 'date' => '2018-01-06 08:59:32', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2018/01/cerano-spring-18-4.jpg', 128, 200, 'cerano spring 18 4').'</p>',
    ],
    [
        'wp_id' => 329, 'slug' => '329-2', 'title' => '', 'date' => '2018-01-06 08:59:10', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2018/01/cerano-spring-18-3.jpg', 128, 200, 'cerano spring 18 3').'</p>',
    ],
    [
        'wp_id' => 326, 'slug' => '326-2', 'title' => '', 'date' => '2018-01-06 08:58:47', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2018/01/cerano-spring-18-2.jpg', 129, 200, 'cerano spring 18 2').'</p>',
    ],
    [
        'wp_id' => 323, 'slug' => 'luisa-cerano-spring-2018', 'title' => 'LUISA CERANO SPRING 2018', 'date' => '2018-01-06 08:58:07', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2018/01/cerano-spring-18-1.jpg', 515, 800, 'cerano spring 18 1').'</p>',
    ],
    [
        'wp_id' => 320, 'slug' => 'luisa-cerano-fall-2017', 'title' => 'LUISA CERANO FALL 2017', 'date' => '2017-08-04 01:29:19', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2017/08/cerano-first-fall-photo-f17.jpg', 515, 800, 'cerano first fall photo f17').'</p>',
    ],
    [
        'wp_id' => null, 'slug' => 'luisa-cerano-athletic-springsummer-2017', 'title' => 'LUISA CERANO ‘ATHLETIC’ SPRING/SUMMER 2017', 'date' => '2017-03-26 12:00:00', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2017/03/LC_Image_FS17_Look_5_130-193x300.jpg', 193, 300, 'LC_Image_FS17_Look_5_130').' '.$img('2017/03/LC_Image_FS17_Look_5_131-193x300.jpg', 193, 300, 'LC_Image_FS17_Look_5_131').' '.$img('2017/03/LC_Image_FS17_Look_5_138-193x300.jpg', 193, 300, 'LC_Image_FS17_Look_5_138').'</p>',
    ],
    [
        'wp_id' => null, 'slug' => 'riani-fall-16', 'title' => 'RIANI FALL ’16', 'date' => '2016-07-26 12:00:00', 'brand' => 'riani',
        'content' => '<p>'.$img('2016/07/2015-11-13-11.33.57-200x300.jpg', 200, 300, 'Riani Fall 2016').$img('2016/07/2015-11-13-14.57.08-1-200x300.jpg', 200, 300, 'Riani Fall 2016').' '.$img('2016/07/2015-11-13-12.23.49-1-200x300.jpg', 200, 300, 'Riani Fall 2016').' '.$img('2016/07/2015-11-13-14.30.16-1-200x300.jpg', 200, 300, 'Riani Fall 2016').'</p>',
    ],
    [
        'wp_id' => null, 'slug' => 'luisa-cerano-fall-16', 'title' => 'LUISA CERANO FALL ’16', 'date' => '2016-07-17 12:00:00', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2016/07/LC_Image_AW16_Look_1_23-193x300.jpg', 193, 300, 'Luisa Cerano Fall 2016').'</p>',
    ],
    [
        'wp_id' => null, 'slug' => 'fall-2016-luisa-cerano-arrived', 'title' => 'FALL 2016 LUISA CERANO HAS ARRIVED', 'date' => '2016-07-07 12:00:00', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2016/07/LC_Image_AW16_Look_1_2-2-193x300.jpg', 193, 300, 'Luisa Cerano Fall 2016').'</p>',
    ],
    [
        'wp_id' => null, 'slug' => 'springsummer-sale-now', 'title' => 'SPRING/SUMMER SALE NOW ON', 'date' => '2016-06-15 12:00:00', 'brand' => 'store-news',
        'content' => $p('PLEASE COME VISIT TO SEE OUR SPRING AND SUMMER COLLECTIONS ON SALE').$p('UP TO 50% OFF ON ALL REMAINING ITEMS').$p('HOPE TO SEE YOU!'),
    ],
    [
        'wp_id' => null, 'slug' => 'laurel-spring-16-nice-collection-as-in-nice-france', 'title' => 'LAUREL SPRING ’16 – NICE COLLECTION (as in Nice, France!)', 'date' => '2016-03-17 12:00:00', 'brand' => 'laurel',
        'content' => '<p>'.$img('2016/03/LAUREL-NICE-COLLECTION-S16-200x300.jpg', 200, 300, "LAUREL NICE COLLECTION S'16").' '.$img('2016/03/LAUREL-S16-DRESS-200x300.jpg', 200, 300, "LAUREL S'16 DRESS").' '.$img('2016/03/LAUREL-TOP-S16-200x300.jpg', 200, 300, "LAUREL TOP S'16").' '.$img('2016/03/LAUREL-WHITE-DRESS-S16-200x300.jpg', 200, 300, "LAUREL WHITE DRESS S'16").'</p>',
    ],
    [
        'wp_id' => null, 'slug' => 'laurel-spring-16', 'title' => 'LAUREL SPRING ’16', 'date' => '2016-02-10 12:00:00', 'brand' => 'laurel',
        'content' => '<p>'.$img('2016/02/Laurel_07-182_sRGB-300x225.jpg', 300, 225, 'Laurel Spring 2016').' '.$img('2016/02/Laurel_02-184_sRGB-225x300.jpg', 225, 300, 'Laurel Spring 2016').' '.$img('2016/02/Laurel_03-269_sRGB-300x225.jpg', 300, 225, 'Laurel Spring 2016').' '.$img('2016/02/Laurel_10-019_sRGB-225x300.jpg', 225, 300, 'Laurel Spring 2016').'</p>',
    ],
    [
        'wp_id' => null, 'slug' => 'luisa-cerano-vancouver-spring-16', 'title' => 'LUISA CERANO SPRING ’16', 'date' => '2016-02-10 11:00:00', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2015/11/LC_Image_SS16_1-193x300.jpg', 193, 300, 'Luisa Cerano Vancouver').' '.$img('2016/02/LC_Image_SS16_5-193x300.jpg', 193, 300, 'Luisa Cerano Vancouver').' '.$img('2016/02/LC_Image_SS16_6-193x300.jpg', 193, 300, 'Luisa Cerano Vancouver').' '.$img('2016/02/LC_Image_SS16_7-193x300.jpg', 193, 300, 'Luisa Cerano Vancouver').' '.$img('2016/02/LC_Image_SS16_9-193x300.jpg', 193, 300, 'Luisa Cerano Vancouver').' '.$img('2016/02/LC_Image_SS16_12-193x300.jpg', 193, 300, 'Luisa Cerano Vancouver').' '.$img('2016/02/LC_Image_SS16_15-193x300.jpg', 193, 300, 'Luisa Cerano Vancouver').'</p>',
    ],
    [
        'wp_id' => null, 'slug' => 'luisa-cerano-fall-15-delivery-4', 'title' => 'LUISA CERANO FALL ’15 – DELIVERY #4', 'date' => '2015-10-09 12:00:00', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2015/10/LC_Image_AW15_-12-212x300.jpg', 212, 300, 'Luisa Cerano Fall').'</p>'
            .$p('Mint Green is FROZEN for Cerano’s grouping to welcome a colder season! Soft sweatering with cool pleather skirts and pants – topped off with fur – of course!'),
    ],
    [
        'wp_id' => 206, 'slug' => '206', 'title' => 'LAUREL PRE-FALL 2015', 'date' => '2015-08-12 12:00:00', 'brand' => 'laurel',
        'content' => '<p>'.$img('2015/08/Laurel_01-041_sRGB-225x300.jpg', 225, 300, 'Coat made in alpaca and wool').'</p>'.$p('LAUREL OCEAN BLUE COAT')
            .'<p>'.$img('2015/08/Laurel_02-108_sRGB-300x225.jpg', 300, 225, 'Laurel_02-108_sRGB').'</p>'.$p('LAUREL OCEAN BLUE/BLACK DRESS')
            .'<p>'.$img('2015/08/Laurel_03-049_sRGB-225x300.jpg', 225, 300, 'Laurel_03-049_sRGB').'</p>'.$p('LAUREL CONTEMPORARY ANIMAL PRINT DRESS'),
    ],
    [
        'wp_id' => null, 'slug' => 'luisa-cerano-pre-fall-2015', 'title' => 'LUISA CERANO PRE-FALL 2015', 'date' => '2015-07-20 12:00:00', 'brand' => 'luisa-cerano',
        'content' => '<p>'.$img('2015/07/LC_Image_AW15_1-212x300.jpg', 212, 300, 'Luisa Cerano Vancouver').'</p>'.$p('Pastel beige wool/cashmere frock coat – soft shawl collar detail – highly versatile – indoor/oudoor wear')
            .'<p>'.$img('2015/07/LC_Image_AW15_5-300x238.jpg', 300, 238, 'Luisa Cerano Vancouver').'</p>'.$p('Flannel grey and gold print dress – wear on it’s own or with sportswear over – blazer/cardigan/pullover/bolero/jacket')
            .'<p>'.$img('2015/07/LC_Image_AW15_6-212x300.jpg', 212, 300, 'Luisa Cerano Vancouver').'</p>'.$p('Flannel gold/grey print pants worn with flannel grey tunic pullover – silk blouse underpinning – note the beige shoes to bring out warmth of pattern'),
    ],
    [
        'wp_id' => null, 'slug' => 'twin-set-spring-2015', 'title' => 'Twin-Set Spring 2015', 'date' => '2015-05-09 12:00:00', 'brand' => 'twin-set',
        'content' => $p('Red makes a big statement for this Spring Season – and paired with the stylish pyjama style pants – all that is needed is the right shoe and bag to take you to your event!')
            .'<p><img src="'.$u('2015/05/IMG_1137-225x300.jpg').'" alt="Twin-Set Spring 2015" width="225" height="300" loading="lazy"></p>',
    ],
    [
        'wp_id' => null, 'slug' => 'save-the-queen-spring-2015', 'title' => 'SAVE THE QUEEN SPRING 2015', 'date' => '2015-04-29 12:00:00', 'brand' => 'save-the-queen',
        'content' => $p('LONG AND FLOWING – LEAVE YOUR PANTS AT HOME!')
            .'<p>'.$img('2015/04/save-the-queen-summer-long-dress-2015-221x300.jpg', 221, 300, 'save the queen summer long dress 2015').'</p>',
    ],
];
