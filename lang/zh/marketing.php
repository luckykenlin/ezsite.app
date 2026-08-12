<?php

declare(strict_types=1);

/*
 * 中央营销站的全部中文文案，键与 lang/en/marketing.php 完全一致
 * （由 tests/Arch/ConventionsTest.php 的键对齐测试守住）。
 *
 * 写给开小生意的人看，不是写给同行看的：不用「赋能」「一站式解决方案」这类词，
 * 句子短，动词具体。行业名（餐馆、美甲、理发）在 templates.php 里。
 */

return [
    'actions' => [
        'browse' => '看看模板',
        'use_template' => '用这套模板',
        'continue' => '继续',
        'back' => '返回',
        // 全站唯一的主动作 —— 页头、首屏、定价卡、页脚重复的都是这一个词。
        'start' => '开始建站',
    ],

    'nav' => [
        'templates' => '模板',
        'pricing' => '价格',
        'language' => '语言',
    ],

    /*
     * 页脚的栏目标题和 logo 下面那一行。栏目里的模板链接直接复用
     * `templates.<slug>.label`，所以栏目永远不会和模板库脱节。
     */
    'footer' => [
        'tagline' => '给小生意的好网站。',
        'product' => '产品',
        'food' => '餐饮模板',
        'services' => '服务与作品',
        'looks' => '版式',
        'faq' => '常见问题',
    ],

    'home' => [
        'meta_description' => '挑一套模板，或让 AI 在我们的设计系统里起草。回答几个关于生意的问题，几分钟后网站就带着自己的网址上线了。',

        'hero' => [
            // 兼作页面的 <title>：标题和搜索结果要许下同一个承诺。
            'title' => '网站上线，生意成真',
            'intro' => '挑一套模板，或让 AI 起草。回答几个关于生意的问题，网站就带着自己的网址上线 —— 照片是真的，文案已经写好，哪个字想改，编辑器一直开着等你。',
            'example' => '看一个做好的例子',
        ],

        /*
         * 数字带。每个数都经得起查：前两个由视图现场算出来
         * （SiteTemplate::libraryCount() 和版式总数），后两个是页面上
         * 别处承诺过的话 —— 产品还年轻，不编用户数。
         */
        'stats' => [
            'templates' => ['label' => '套做完的模板'],
            'looks' => ['label' => '种版式，一套设计系统'],
            'minutes' => ['value' => '≈4 分钟', 'label' => '从挑模板到上线'],
            'drag' => ['value' => '0', 'label' => '次拖拽'],
        ],

        /*
         * 网站能干的活，一张卡一个动词 —— 严格限于已经做出来的功能
         * （页面、动态、咨询、门店、SEO、域名）。这里不许承诺
         * 卖货、收款或预订。
         */
        'jobs' => [
            'title' => '让网站替你干活',
            'intro' => '不是一张电子名片，是替小生意把活干了的网站。',
            'items' => [
                'menu' => [
                    'title' => '展示菜单和价格',
                    'body' => '菜单板块带价格，注册时顺手填好。',
                ],
                'leads' => [
                    'title' => '收下每一条咨询',
                    'body' => '表单收到的留言直接进你的后台，一条不丢。',
                ],
                'find' => [
                    'title' => '让客人找到门店',
                    'body' => '地址、营业时间、电话，都绑在真实的店上。',
                ],
                'posts' => [
                    'title' => '发布近况和优惠',
                    'body' => '新菜、活动、放假通知，一分钟就发出去。',
                ],
                'seo' => [
                    'title' => '让搜索引擎看见你',
                    'body' => '标题、描述、站点地图，都替你配好了。',
                ],
                'domain' => [
                    'title' => '用自己的域名',
                    'body' => '先在免费网址上线，随时换成你自己的。',
                ],
            ],
        ],

        /*
         * 两条平等的入口。AI 那张卡讲的是「设计系统里的 AI」，
         * 不是技术本身 —— 两张卡都老老实实指向模板库，因为两条路
         * 确实都要经过挑模板和注册向导。
         */
        'paths' => [
            'eyebrow' => '零经验也行',
            'title' => '两条路，都通到上线',
            'ai' => [
                'title' => '让 AI 起草',
                'body' => '回答几个关于生意的问题，AI 就在我们的设计系统里把整站起草出来 —— 版式、文案、板块一次到位，哪个字不喜欢都能改。',
                'cta' => '用 AI 开始',
            ],
            'template' => [
                'title' => '从模板开始',
                'body' => ':count 套做完的真网站，每一套都懂你这行。打开在线演示，几分钟变成你的。',
                'cta' => '去挑一套',
            ],
        ],

        'how' => [
            'eyebrow' => '怎么用',
            'title' => '三步，没有一步是「拖拽方框」',
            'steps' => [
                'pick' => [
                    'title' => '挑一套模板',
                    'body' => ':count 个行业，每一套都是做完的网站，不是线框图。定下来之前，先打开在线演示看看。',
                ],
                'answer' => [
                    'title' => '回答几个问题',
                    'body' => '店名、地址、几样你卖的东西。哪一项不想填就跳过，模板里的示例文案会留着。',
                ],
                'live' => [
                    'title' => '直接上线',
                    'body' => '网站立刻跑在 yourname.:host 上，编辑器就停在首页等你。',
                ],
            ],
        ],

        'templates' => [
            'eyebrow' => '模板',
            'title' => '从一个已经懂你这行的网站开始',
            'intro' => '每一套都是现在就能打开的真网站，不是一张想象出来的截图。',
        ],

        'design' => [
            'eyebrow' => '一套设计系统',
            'title' => ':count 种版式，想做丑都难',
            'intro' => '你不用挑字号，也不用填色号。你只挑一种版式，每一页的每一屏都跟着它走 —— 标题、间距、圆角，全都算好了。',
        ],

        /*
         * 「都是真网站」那面墙：我们的社会证明就是 demo 站本身 ——
         * 已发布、现在就在线上跑着，不是想象出来的截图。
         */
        'made' => [
            'eyebrow' => '都是真网站',
            'title' => '每一套，现在就在线上跑着',
            'intro' => '不是效果图。点开哪一个，都是一个已发布的网站，跑在自己的网址上。',
            'all' => '看全部 :count 个在线演示',
        ],

        /*
         * 占位 testimonial —— 上线前必须换成真实客户的话。
         * 不放头像照，理由和模板里那句「没有素材照假装是你」一样。
         */
        'quotes' => [
            'title' => '开店的人怎么说',
            'items' => [
                'one' => [
                    'quote' => '周五晚上订座的电话，比以前多了一倍。',
                    'name' => '陈姐',
                    'trade' => '川菜馆',
                ],
                'two' => [
                    'quote' => '客人现在会先看菜单再进门，点单快多了。',
                    'name' => '老王',
                    'trade' => '披萨店',
                ],
                'three' => [
                    'quote' => '我不懂设计，但没有一个客人看得出来。',
                    'name' => 'Amy',
                    'trade' => '美甲店',
                ],
            ],
        ],

        /*
         * 定价预览。`price` 是占位数字 —— 上线前定下真实价格。
         * 只有一档：年轻产品摆三列自己比自己，看着就像演戏。
         */
        'pricing' => [
            'eyebrow' => '价格',
            'title' => '一个数，全包了',
            'intro' => '先建站，看到成品再决定 —— 不用填卡号。',
            'price' => '¥99',
            'period' => '每月',
            'note' => '所有功能、所有模板、所有版式都在里面，没有档位要比。',
            'includes' => [
                'address' => '你自己的网址',
                'templates' => '全部模板和版式',
                'editor' => '可视化编辑器',
                'seo' => '配好的 SEO',
                'forms' => '进后台的咨询表单',
            ],
        ],

        /*
         * 常见疑虑，按人们真正开口的顺序排。`:host` 和 `:count`
         * 由视图替换，写法同 `how.steps`。
         */
        'faq' => [
            'title' => '你可能想问',
            'items' => [
                'speed' => [
                    'q' => '上线要多久？',
                    'a' => '几分钟。挑一套模板，回答几个问题，网站就跑在 yourname.:host 上了。',
                ],
                'design' => [
                    'q' => '我不懂设计，做出来会不会丑？',
                    'a' => '不会。你只挑一种版式，字号、间距、配色都替你算好了 —— :count 种版式，随时整站切换。',
                ],
                'domain' => [
                    'q' => '可以用自己的域名吗？',
                    'a' => '可以。先用免费网址上线，之后随时绑定你自己的域名。',
                ],
                'ai' => [
                    'q' => 'AI 写的字会不会很假？',
                    'a' => 'AI 只在你这行的模板和我们的设计系统里起草，每个字都能在编辑器里改。跳过的问题，写好的示例文案会留着。',
                ],
                'edit' => [
                    'q' => '上线之后还能改吗？',
                    'a' => '一直能改。编辑器随时开着 —— 改一个字、换一张照片、换整套版式都行。',
                ],
                'seo' => [
                    'q' => '搜索引擎能搜到我吗？',
                    'a' => '能。每个网站自带 SEO：标题、描述、站点地图都替你配好了。',
                ],
                'pay' => [
                    'q' => '要先付钱吗？',
                    'a' => '不用。不填卡号，网站先上线，看到成品再决定。',
                ],
                'leads' => [
                    'q' => '客人能在网站上联系我吗？',
                    'a' => '能。表单收到的每一条留言都会进你的后台。',
                ],
            ],
        ],

        'cta' => [
            'title' => '离你的网站，大概四分钟',
            'intro' => '不用付卡，不用通话，也没有空白页。挑一套合适的模板，开始往里填。',
        ],
    ],

    'gallery' => [
        'meta_title' => '小生意能直接用的网站模板',
        'meta_description' => ':count 套做完的网站，一行一套。先打开在线演示，再花几分钟把它变成你的。',
        'eyebrow' => '模板',
        'title' => ':count 套做完的网站，挑一套合你的。',
        'intro' => '每一套都是已经发布的真网站，不是线框图 —— 打开在线演示，读一读文案，用手机滑一遍。找到对的那套，几分钟就能变成你的。',
    ],

    'card' => [
        'alt' => ':template 模板',
        'cta' => '看这套模板',
    ],

    'detail' => [
        'meta_title' => ':template 网站模板',
        'back' => '全部模板',
        'phone_alt' => ':template 模板在手机上的样子',
        'iframe_title' => ':template 模板的在线演示',

        'look' => [
            'title' => '这套版式',
            'note' => '什么时候想换都行 —— 每一页的每一屏都会跟着你挑的版式走。',
        ],

        'pages' => [
            'title' => '你会拿到什么',
            'sections' => ':count 个板块',
        ],

        'questions' => [
            'title' => '我们会问你什么',
            'note' => '全都可以不填 —— 跳过的话，示例文案会留在那儿。',
        ],

        'mobile' => [
            'title' => '在手机上一样好读',
        ],

        'demo' => [
            'title' => '在线演示',
            'served_from' => '这就是那个真网站，跑在 :host 上。',
            'view' => '看在线演示',
            'open' => '打开在线演示',
        ],

        'cta' => [
            'title' => '把它变成你的',
            'intro' => '回答几个问题，这个网站就带着你自己的网址上线，随时能改。',
        ],
    ],

    'wizard' => [
        'title' => '来把你的网站建起来',
        'progress' => '进度',
        'optional' => '（可不填）',

        'steps' => [
            'business' => '你的生意',
            'content' => '你的内容',
            'account' => '你的账号',
        ],

        'business_name' => '这家店叫什么？',
        'address' => '你的网址',
        'use_suggestion' => '换用 :subdomain',
        'available' => ':domain 还没人用',
        'tagline' => '一句话说清这家店',
        'city' => '所在城市',
        'phone' => '电话',

        'content_intro' => '这些会直接出现在你的页面上。哪一项留空、或者整步跳过都行 —— 模板里的示例内容会一直留着，直到你在编辑器里改掉它。',
        'skip' => '跳过 —— 先用示例内容，以后再改',

        'account_intro' => '最后一步。以后你就用这个账号登录，改自己的网站。',
        'email' => '邮箱',
        'password' => '密码',
        'honeypot' => '网址',
        'submit' => '开始建站',
        'submitting' => '正在建站…',
        'rate_limited' => '这条网络建的站太多了，请 :minutes 分钟后再试。',

        'done' => [
            'eyebrow' => '你的网站已经上线',
            'title' => ':business 已经在网上了',
            'published' => '它发布在 :url。',
            'drafts' => '页面先存成了草稿，你可以趁别人还没看到先读一遍 —— 打开编辑器，觉得没问题就点发布。照片还在陆续到位，稍等一分钟。',
            'open_editor' => '打开编辑器',
            'view_site' => '看我的网站',
        ],
    ],

    'not_found' => [
        'title' => '页面不存在',
        'body' => '这个链接没有指向任何地方。不如从模板开始看。',
        'cta' => '看看模板',
    ],

    'subdomain' => [
        'invalid' => '请用 3 到 63 个字母、数字或连字符，比如 "corner-cafe"。',
        'reserved' => '这个网址是留用的，请换一个。',
        'taken' => '这个网址已经有人用了。',
    ],

    /*
     * 每套模板的中文文案：名字、卡片上那句话、详情页的三条理由，以及向导会问的问题。
     * 键与 lang/en/marketing.php 的同一段完全一致。
     *
     * 不在这里的：模板自己的页面内容，以及每个问题后面的示例值（example）。
     * 那些会在套用模板时写进租户的 pages 表，属于站点内容而不是界面文案。
     */
    'templates' => [
        'chinese-restaurant' => [
            'label' => '中餐馆',
            'description' => '一份菜单、一段故事、一张地图。为周五晚上会坐满的家庭厨房而做。',
            'highlights' => [
                '带价格的菜单板块，注册时顺手填好',
                '地址、营业时间、电话都绑在你真实的门店上',
                '「我们的故事」那一页，文案已经写好了',
            ],
            'fields' => [
                'dish_one' => ['label' => '你的招牌菜'],
                'dish_one_price' => ['label' => '价格'],
                'dish_two' => ['label' => '第二道常点的'],
                'dish_two_price' => ['label' => '价格'],
                'dish_three' => ['label' => '再来一道'],
                'dish_three_price' => ['label' => '价格'],
            ],
        ],

        'dim-sum-house' => [
            'label' => '点心茶楼',
            'description' => '金边菜单卡、分类页签、聚光灯下的烤鸭。为菜单本身就是招牌的茶楼而做。',
            'highlights' => [
                '带饰框、可筛选的菜单卡，辣、素、无麸质一目了然',
                '「主厨推荐」聚光位，留给你最拿得出手的那道菜',
                '内建订座表单，人数、日期一次填好',
            ],
            'fields' => [
                'dish_one' => ['label' => '你的招牌点心'],
                'dish_one_price' => ['label' => '价格'],
                'dish_two' => ['label' => '第二道常点的'],
                'dish_two_price' => ['label' => '价格'],
                'dish_three' => [
                    'label' => '值得主推的那道菜',
                    'help' => '它会带着照片出现在菜单最顶上的聚光位。',
                ],
                'dish_three_price' => ['label' => '价格'],
            ],
        ],

        'pizza-shop' => [
            'label' => '披萨店',
            'description' => '热闹、喧腾、勾人食欲。街坊一周点两次的那家披萨店。',
            'highlights' => [
                '三款招牌披萨连价格，直接从表单填进去',
                '每一页都有「打电话点单」的横幅',
                '照片专门挑了柴火炉的暖调',
            ],
            'fields' => [
                'pizza_one' => [
                    'label' => '你最出名的那款披萨',
                    'help' => '它排在菜单最前面，所以挑你最想让第一次来的人点的那款。',
                ],
                'pizza_one_price' => ['label' => '价格'],
                'pizza_two' => ['label' => '第二款披萨'],
                'pizza_two_price' => ['label' => '价格'],
                'pizza_three' => ['label' => '再来一款'],
                'pizza_three_price' => ['label' => '价格'],
            ],
        ],

        'burger-joint' => [
            'label' => '汉堡店',
            'description' => '大字、大图、不绕弯子。为门口总排着队的柜台而做。',
            'highlights' => [
                '标题字号是配大图的尺寸，不是配段落的',
                '菜单板块可以一路加到整块菜单牌',
                '文案写得直白随性，照原样留着就行',
            ],
            'fields' => [
                'burger_one' => [
                    'label' => '你的招牌汉堡',
                    'help' => '客人一进门就会念出名字的那个。',
                ],
                'burger_one_price' => ['label' => '价格'],
                'burger_two' => ['label' => '第二款汉堡'],
                'burger_two_price' => ['label' => '价格'],
                'burger_three' => ['label' => '再来一款'],
                'burger_three_price' => ['label' => '价格'],
            ],
        ],

        'bubble-tea' => [
            'label' => '奶茶店',
            'description' => '明快、以产品为主，留好了每月换一次的季节菜单位置。',
            'highlights' => [
                '季节限定板块，生来就是给你每月换的',
                '粉彩系的产品照，从共享图库里取',
                '干净的几何网格，饮品名字再长也不乱',
            ],
            'fields' => [
                'drink_one' => [
                    'label' => '你的招牌饮品',
                    'help' => '会摆在窗口那杯。它排在菜单最前面。',
                ],
                'drink_one_price' => ['label' => '价格'],
                'drink_two' => ['label' => '第二款常点的'],
                'drink_two_price' => ['label' => '价格'],
                'seasonal_drink' => ['label' => '现在在推什么'],
                'seasonal_drink_price' => ['label' => '价格'],
            ],
        ],

        'fine-dining' => [
            'label' => '高级餐厅',
            'description' => '深色、安静、以订座为先。为那种要提前一周订位的餐厅而做。',
            'highlights' => [
                '订座表单，日期、时间、人数一次填好',
                '三幕式菜单，分组标题配价格',
                '每个到店板块都带嵌入地图和实时营业状态',
            ],
            'fields' => [
                'starter_dish' => [
                    'label' => '你最出名的前菜',
                    'help' => '它排在菜单最前面——选那道常客不许你下架的。',
                ],
                'starter_dish_price' => ['label' => '价格'],
                'main_dish' => ['label' => '压轴主菜'],
                'main_dish_price' => ['label' => '价格'],
                'dessert_dish' => ['label' => '收尾的甜品'],
                'dessert_dish_price' => ['label' => '价格'],
            ],
        ],

        'sushi-bar' => [
            'label' => '寿司店',
            'description' => '克制、精确、留白充分。为一张十二个座位的板前吧台而做。',
            'highlights' => [
                '订位表单，按板前吧台的规模设计',
                '菜单按 Omakase、握寿司、卷物分组',
                '安静的渐显动效，和店里的节奏一致',
            ],
            'fields' => [
                'omakase_name' => [
                    'label' => '你的 Omakase，按菜单上的叫法',
                    'help' => '吧台的招牌套餐——它排在菜单最前面。',
                ],
                'omakase_price' => ['label' => '每位价格'],
                'nigiri_one' => ['label' => '一贯你自豪的握寿司'],
                'nigiri_one_price' => ['label' => '价格'],
                'roll_one' => ['label' => '一道客人会回头点的卷物'],
                'roll_one_price' => ['label' => '价格'],
            ],
        ],

        'cafe-brunch' => [
            'label' => '咖啡·早午餐',
            'description' => '明亮、温暖、属于早晨。工作日随到随坐，周末早午餐订满。',
            'highlights' => [
                '周末早午餐订位表单，日期、时间、人数齐全',
                '菜单按早午餐、烘焙、咖啡分组',
                '页中的会员注册板块，放你的常客优惠',
            ],
            'fields' => [
                'brunch_one' => [
                    'label' => '你最出名的早午餐',
                    'help' => '它排在菜单最前面——客人先拍照再动叉的那盘。',
                ],
                'brunch_one_price' => ['label' => '价格'],
                'brunch_two' => ['label' => '第二道常点的'],
                'brunch_two_price' => ['label' => '价格'],
                'signature_coffee' => ['label' => '必点的那杯咖啡'],
                'signature_coffee_price' => ['label' => '价格'],
            ],
        ],

        'family-bistro' => [
            'label' => '家庭餐馆',
            'description' => '温暖、大方、不端着——过生日和周日午餐都订这张邻里餐桌。',
            'highlights' => [
                '订座表单，按一大家子的规模设计',
                '菜单自带儿童餐分组',
                '文案为生日、常客和周日午餐写好了',
            ],
            'fields' => [
                'main_one' => [
                    'label' => '大家都点的那道主菜',
                    'help' => '它排在菜单最前面——一桌人边吃边聊的那盘。',
                ],
                'main_one_price' => ['label' => '价格'],
                'main_two' => ['label' => '第二道常点的'],
                'main_two_price' => ['label' => '价格'],
                'kids_meal' => ['label' => '小朋友吃什么'],
                'kids_meal_price' => ['label' => '价格'],
            ],
        ],

        'nail-salon' => [
            'label' => '美甲店',
            'description' => '暗色房间、金色点缀，往下滑一屏就是你的作品集。',
            'highlights' => [
                '首屏往下紧接着就是美甲作品集',
                '带价格的服务表，注册时填好',
                '库里唯一的深色版式，配金色点缀',
            ],
            'fields' => [
                'service_one' => [
                    'label' => '你最拿手的那项服务',
                    'help' => '它排在两个页面的服务表最前面。',
                ],
                'service_one_price' => ['label' => '价格'],
                'service_two' => ['label' => '第二项服务'],
                'service_two_price' => ['label' => '价格'],
                'service_three' => ['label' => '一项做足部的'],
                'service_three_price' => ['label' => '价格'],
            ],
        ],

        'hair-studio' => [
            'label' => '发型工作室',
            'description' => '整整一屏只有字，一张照片都不放。为「排期就是招牌」的工作室而做。',
            'highlights' => [
                '开场满屏只有字 —— 首屏不放任何素材照',
                '剪发与染色的服务表，时长和价格都是真的',
                '库里唯一会随着滚动、板块依次到场的版式',
            ],
            'fields' => [
                'service_one' => ['label' => '预约最多的那项服务'],
                'service_one_price' => [
                    'label' => '做多久、多少钱',
                    'help' => '先写时长再写价格 —— 在服务表里是一行读完的。',
                ],
                'service_two' => ['label' => '第二项服务'],
                'service_two_price' => ['label' => '时长和价格'],
                'service_three' => ['label' => '再来一项'],
                'service_three_price' => ['label' => '时长和价格'],
            ],
        ],

        'massage-spa' => [
            'label' => '按摩与养生',
            'description' => '开阔、不催人，只留一条安静的预约路径。整页没有一处在提高音量。',
            'highlights' => [
                '高挑安静的首屏，只有一个行动按钮',
                '带时长和价格的项目表',
                '间距是调过的，整页没有一处催你',
            ],
            'fields' => [
                'treatment_one' => ['label' => '预约最多的那个项目'],
                'treatment_one_price' => [
                    'label' => '做多久、多少钱',
                    'help' => '先写时长再写价格 —— 在项目表里是一行读完的。',
                ],
                'treatment_two' => ['label' => '第二个项目'],
                'treatment_two_price' => ['label' => '时长和价格'],
                'treatment_three' => ['label' => '再来一个'],
                'treatment_three_price' => ['label' => '时长和价格'],
            ],
        ],

        'personal-resume' => [
            'label' => '个人简历',
            'description' => '你的名字、你做过的事，以及找到你的方式。只有字，看不到一张素材照。',
            'highlights' => [
                '只有字的首屏：名字、头衔、一句话',
                '三段经历，注册时填好',
                '没有素材照假装是你',
            ],
            'fields' => [
                'current_title' => [
                    'label' => '你现在的头衔',
                    'help' => '一句话。它就排在首屏你名字的下面。',
                ],
                'role_one' => ['label' => '现在或最近的一份工作'],
                'role_two' => ['label' => '上一份'],
                'role_three' => ['label' => '再上一份'],
                'focus' => ['label' => '你想多做的那类事'],
            ],
        ],

        'designer-portfolio' => [
            'label' => '设计师作品集',
            'description' => '杂志式排版，作品就在第一屏。为靠作品说话的工作室而做。',
            'highlights' => [
                '作品在黑底上，就在第一屏',
                '三个项目的介绍，从注册表单填进去',
                '服务页写的是真实的合作方式和价格',
            ],
            'fields' => [
                'discipline' => [
                    'label' => '你到底做什么',
                    'help' => '一句话。它排在首屏你名字的下面。',
                ],
                'project_one' => ['label' => '拿来打头的项目'],
                'project_two' => ['label' => '第二个项目'],
                'project_three' => ['label' => '第三个项目'],
            ],
        ],
    ],

    /*
     * 版式在营销站上的中文说法，键与 lang/en/marketing.php 的同一段一致。
     *
     * 这份文案是营销站专用的分叉：StylePreset::label()/description() 仍然是英文字面量，
     * 因为它们同时喂给两个 AI 系统提示（SiteDraftPrompt、PageEditPrompt），
     * 模型是靠那段英文判断「再高级一点」该选哪套版式的。详见 lang/en/marketing.php 里这一段的英文注释。
     */
    'presets' => [
        'warm-craft' => [
            'label' => '温暖手作',
            'description' => '土系色调、优雅衬线字、留白充足 —— 适合手作与餐饮小店。',
        ],
        'professional-minimal' => [
            'label' => '专业极简',
            'description' => '黑白灰、紧凑、以字为主 —— 适合靠信任成交的服务。',
        ],
        'fresh-modern' => [
            'label' => '清爽现代',
            'description' => '偏绿的几何感，节奏明快 —— 适合有现代感的品牌。',
        ],
        'bold-editorial' => [
            'label' => '大胆杂志',
            'description' => '高对比的梅子色、利落直角、杂志式排版。',
        ],
        'calm-coastal' => [
            'label' => '海边安静',
            'description' => '清冷蓝、柔和圆角、开阔留白 —— 适合康养与照护。',
        ],
        'playful-friendly' => [
            'label' => '活泼亲切',
            'description' => '落日色、圆润造型，说话也友好。',
        ],
        'night-lounge' => [
            'label' => '夜店暗调',
            'description' => '近黑配金，暗色房间里的厚重字体 —— 库里唯一的深色版式。',
        ],
        'quiet-luxe' => [
            'label' => '安静高级',
            'description' => '暖石色，发丝般的衬线字放得很大，直角收边，板块随着滚动依次到场。',
        ],
    ],
];
