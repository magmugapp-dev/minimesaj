<?php

return [
    'models' => [
        'gemini-2.5-flash' => 'Gemini 2.5 Flash',
    ],

    'account_statuses' => [
        'aktif' => 'Aktif',
        'pasif' => 'Pasif',
        'yasakli' => 'Yasakli',
    ],

    'genders' => [
        'kadin' => 'Kadin',
        'erkek' => 'Erkek',
        'belirtmek_istemiyorum' => 'Belirtmek istemiyorum',
    ],

    'languages' => [
        'tr' => 'Turkce',
        'en' => 'Ingilizce',
        'de' => 'Almanca',
        'fr' => 'Fransizca',
        'it' => 'Italyanca',
        'es' => 'Ispanyolca',
        'ru' => 'Rusca',
        'ar' => 'Arapca',
        'hi' => 'Hintce',
        'ja' => 'Japonca',
        'ko' => 'Korece',
        'pt' => 'Portekizce',
    ],

    'conversation_tones' => [
        'dogal' => 'Dogal',
        'samimi' => 'Samimi',
        'sicak' => 'Sicak',
        'neseli' => 'Neseli',
        'olgun' => 'Olgun',
        'mesafeli' => 'Mesafeli',
        'flortoz' => 'Flortoz',
        'nazik' => 'Nazik',
        'karizmatik' => 'Karizmatik',
        'merakli' => 'Merakli',
    ],

    'conversation_styles' => [
        'akici' => 'Akici',
        'kisa' => 'Kisa',
        'samimi' => 'Samimi',
        'oyuncu' => 'Oyuncu',
        'direkt' => 'Direkt',
        'rahat' => 'Rahat',
        'duygulu' => 'Duygulu',
        'sade' => 'Sade',
        'zarif' => 'Zarif',
        'enerjik' => 'Enerjik',
    ],

    'lifestyles' => [
        'Sehirli',
        'Sakin',
        'Gezgin',
        'Kariyer odakli',
        'Sanat odakli',
        'Sosyal',
        'Evine duskun',
        'Spora yakin',
        'Gece hayati seven',
        'Dogayla ic ice',
    ],

    'professions' => [
        'Grafik tasarimci',
        'Yazilim gelistirici',
        'Ogretmen',
        'Doktor',
        'Hemsire',
        'Avukat',
        'Mimar',
        'Pazarlama uzmani',
        'Fotografci',
        'Icerik ureticisi',
        'Psikolog',
        'Girisimci',
    ],

    'sectors' => [
        'Tasarim',
        'Teknoloji',
        'Egitim',
        'Saglik',
        'Hukuk',
        'Mimarlik',
        'Pazarlama',
        'Medya',
        'Finans',
        'Turizm',
        'Sanat',
        'Girisim',
    ],

    'education_levels' => [
        'Lise',
        'On lisans',
        'Lisans',
        'Yuksek lisans',
        'Doktora',
    ],

    'age_ranges' => [
        '18-22',
        '23-27',
        '28-32',
        '33-37',
        '38-42',
        '43+',
    ],

    'relationship_history_tones' => [
        'Denge arayan',
        'Yaralari sarilmis',
        'Temkinli ama acik',
        'Romantik bakisli',
        'Macera arayan',
        'Ciddi iliski odakli',
    ],

    'response_rhythms' => [
        'Cok hizli',
        'Hizli',
        'Dengeli',
        'Dusunerek',
        'Agir ama istikrarli',
    ],

    'emoji_habits' => [
        'Neredeyse hic kullanmaz',
        'Nadiren kullanir',
        'Yerinde kullanir',
        'Sik kullanir',
        'Oldukca belirgin kullanir',
    ],

    'behavior_sliders' => [
        'mizah_seviyesi' => [
            'label' => 'Mizah',
            'group' => 'Sosyal Tarz',
            'legend' => ['Duz', 'Canli'],
            'default' => 5,
        ],
        'flort_seviyesi' => [
            'label' => 'Flort',
            'group' => 'Iliski Dinamigi',
            'legend' => ['Dusuk', 'Yuksek'],
            'default' => 4,
        ],
        'emoji_seviyesi' => [
            'label' => 'Emoji',
            'group' => 'Sosyal Tarz',
            'legend' => ['Yok', 'Serbest'],
            'default' => 3,
        ],
        'giriskenlik_seviyesi' => [
            'label' => 'Giriskenlik',
            'group' => 'Iliski Dinamigi',
            'legend' => ['Pasif', 'One cikan'],
            'default' => 5,
        ],
        'utangaclik_seviyesi' => [
            'label' => 'Utangaclik',
            'group' => 'Iliski Dinamigi',
            'legend' => ['Acik', 'Cekingen'],
            'default' => 3,
        ],
        'duygusallik_seviyesi' => [
            'label' => 'Duygusallik',
            'group' => 'Karakter Durusu',
            'legend' => ['Net', 'Duygusal'],
            'default' => 5,
        ],
        'argo_seviyesi' => [
            'label' => 'Argo',
            'group' => 'Sosyal Tarz',
            'legend' => ['Yok', 'Belirgin'],
            'default' => 2,
        ],
        'sicaklik_seviyesi' => [
            'label' => 'Sicaklik',
            'group' => 'Sohbet Enerjisi',
            'legend' => ['Mesafeli', 'Sicak'],
            'default' => 6,
        ],
        'empati_seviyesi' => [
            'label' => 'Empati',
            'group' => 'Sohbet Enerjisi',
            'legend' => ['Dusuk', 'Yuksek'],
            'default' => 6,
        ],
        'merak_seviyesi' => [
            'label' => 'Merak',
            'group' => 'Sohbet Enerjisi',
            'legend' => ['Sabit', 'Sorgulayan'],
            'default' => 6,
        ],
        'ozguven_seviyesi' => [
            'label' => 'Ozguven',
            'group' => 'Karakter Durusu',
            'legend' => ['Temkinli', 'Kendinden emin'],
            'default' => 5,
        ],
        'sabir_seviyesi' => [
            'label' => 'Sabir',
            'group' => 'Sohbet Enerjisi',
            'legend' => ['Cabuk sikilan', 'Dayanikli'],
            'default' => 6,
        ],
        'baskinlik_seviyesi' => [
            'label' => 'Baskinlik',
            'group' => 'Karakter Durusu',
            'legend' => ['Yumusak', 'Baskin'],
            'default' => 3,
        ],
        'sarkastiklik_seviyesi' => [
            'label' => 'Sarkastiklik',
            'group' => 'Sosyal Tarz',
            'legend' => ['Yok', 'Keskin'],
            'default' => 2,
        ],
        'romantizm_seviyesi' => [
            'label' => 'Romantizm',
            'group' => 'Iliski Dinamigi',
            'legend' => ['Sade', 'Romantik'],
            'default' => 4,
        ],
        'oyunculuk_seviyesi' => [
            'label' => 'Oyunculuk',
            'group' => 'Sosyal Tarz',
            'legend' => ['Sakin', 'Oyuncu'],
            'default' => 5,
        ],
        'ciddiyet_seviyesi' => [
            'label' => 'Ciddiyet',
            'group' => 'Karakter Durusu',
            'legend' => ['Rahat', 'Ciddi'],
            'default' => 5,
        ],
        'gizem_seviyesi' => [
            'label' => 'Gizem',
            'group' => 'Karakter Durusu',
            'legend' => ['Acik', 'Kapali'],
            'default' => 4,
        ],
        'hassasiyet_seviyesi' => [
            'label' => 'Hassasiyet',
            'group' => 'Karakter Durusu',
            'legend' => ['Kalin derili', 'Ince dusunen'],
            'default' => 5,
        ],
        'enerji_seviyesi' => [
            'label' => 'Enerji',
            'group' => 'Sohbet Enerjisi',
            'legend' => ['Durgun', 'Yuksek'],
            'default' => 5,
        ],
        'kiskanclik_seviyesi' => [
            'label' => 'Kiskanclik',
            'group' => 'Iliski Dinamigi',
            'legend' => ['Sakin', 'Sahiplenen'],
            'default' => 2,
        ],
        'zeka_seviyesi' => [
            'label' => 'Zeka',
            'group' => 'Karakter Durusu',
            'legend' => ['Gundelik', 'Analitik'],
            'default' => 6,
        ],
    ],

    'location_catalog' => [
        'Turkiye' => [
            'regions' => [
                'Marmara' => ['Istanbul', 'Bursa', 'Kocaeli'],
                'Ege' => ['Izmir', 'Mugla', 'Aydin'],
                'Ic Anadolu' => ['Ankara', 'Konya', 'Eskisehir'],
                'Akdeniz' => ['Antalya', 'Adana', 'Mersin'],
            ],
        ],
        'ABD' => [
            'regions' => [
                'Kuzeydogu' => ['New York', 'Boston', 'Philadelphia'],
                'Guney' => ['Miami', 'Austin', 'Atlanta'],
                'Bati' => ['Los Angeles', 'San Francisco', 'Seattle'],
            ],
        ],
        'Almanya' => [
            'regions' => [
                'Bati Almanya' => ['Berlin', 'Koln', 'Dusseldorf'],
                'Guney Almanya' => ['Munih', 'Stuttgart', 'Nurnberg'],
                'Kuzey Almanya' => ['Hamburg', 'Bremen', 'Hanover'],
            ],
        ],
        'Fransa' => [
            'regions' => [
                'Ile-de-France' => ['Paris', 'Versailles', 'Boulogne-Billancourt'],
                'Guney Fransa' => ['Marsilya', 'Nice', 'Montpellier'],
                'Bati Fransa' => ['Nantes', 'Bordeaux', 'Toulouse'],
            ],
        ],
        'Italya' => [
            'regions' => [
                'Kuzey Italya' => ['Milano', 'Torino', 'Venedik'],
                'Orta Italya' => ['Roma', 'Floransa', 'Pisa'],
                'Guney Italya' => ['Napoli', 'Bari', 'Palermo'],
            ],
        ],
        'Ispanya' => [
            'regions' => [
                'Merkez Ispanya' => ['Madrid', 'Toledo', 'Segovia'],
                'Akdeniz Ispanya' => ['Barselona', 'Valensiya', 'Alicante'],
                'Guney Ispanya' => ['Sevilla', 'Malaga', 'Granada'],
            ],
        ],
        'Birlesik Krallik' => [
            'regions' => [
                'Ingiltere' => ['Londra', 'Manchester', 'Birmingham'],
                'Iskocya' => ['Edinburgh', 'Glasgow', 'Aberdeen'],
                'Galler ve Kuzey Irlanda' => ['Cardiff', 'Belfast', 'Swansea'],
            ],
        ],
        'Hollanda' => [
            'regions' => [
                'Randstad' => ['Amsterdam', 'Rotterdam', 'Lahey'],
                'Gunay Hollanda' => ['Eindhoven', 'Maastricht', 'Tilburg'],
                'Kuzey Hollanda' => ['Utrecht', 'Groningen', 'Leeuwarden'],
            ],
        ],
        'Rusya' => [
            'regions' => [
                'Bati Rusya' => ['Moskova', 'St. Petersburg', 'Kazan'],
                'Guney Rusya' => ['Sochi', 'Rostov-on-Don', 'Krasnodar'],
                'Sibirya' => ['Novosibirsk', 'Omsk', 'Irkutsk'],
            ],
        ],
        'Ukrayna' => [
            'regions' => [
                'Merkez Ukrayna' => ['Kiev', 'Dnipro', 'Cherkasy'],
                'Bati Ukrayna' => ['Lviv', 'Ivano-Frankivsk', 'Ternopil'],
                'Guney Ukrayna' => ['Odessa', 'Mykolaiv', 'Kherson'],
            ],
        ],
        'Brezilya' => [
            'regions' => [
                'Guneydogu' => ['Sao Paulo', 'Rio de Janeiro', 'Belo Horizonte'],
                'Kuzeydogu' => ['Salvador', 'Recife', 'Fortaleza'],
                'Guney' => ['Curitiba', 'Porto Alegre', 'Florianopolis'],
            ],
        ],
        'Meksika' => [
            'regions' => [
                'Merkez' => ['Meksiko', 'Puebla', 'Toluca'],
                'Kuzey' => ['Monterrey', 'Tijuana', 'Chihuahua'],
                'Guney' => ['Guadalajara', 'Merida', 'Oaxaca'],
            ],
        ],
        'Arjantin' => [
            'regions' => [
                'Merkez' => ['Buenos Aires', 'La Plata', 'Rosario'],
                'Kuzey' => ['Cordoba', 'Salta', 'Tucuman'],
                'Guney' => ['Mendoza', 'Bariloche', 'Ushuaia'],
            ],
        ],
        'Fas' => [
            'regions' => [
                'Kuzey Fas' => ['Tanca', 'Tetuan', 'Chefchaouen'],
                'Merkez Fas' => ['Rabat', 'Kazablanka', 'Fes'],
                'Guney Fas' => ['Marrakesh', 'Agadir', 'Ouarzazate'],
            ],
        ],
        'Misir' => [
            'regions' => [
                'Kuzey Misir' => ['Kahire', 'Giza', 'Iskenderiye'],
                'Nil Hatti' => ['Luxor', 'Aswan', 'Minya'],
                'Kizildeniz' => ['Hurghada', 'Sharm El Sheikh', 'Suez'],
            ],
        ],
        'Suudi Arabistan' => [
            'regions' => [
                'Merkez' => ['Riyad', 'Buraydah', 'Al Kharj'],
                'Bati' => ['Cidde', 'Mekke', 'Medine'],
                'Dogu' => ['Dammam', 'Khobar', 'Dhahran'],
            ],
        ],
        'Hindistan' => [
            'regions' => [
                'Kuzey Hindistan' => ['Delhi', 'Jaipur', 'Lucknow'],
                'Bati Hindistan' => ['Mumbai', 'Pune', 'Ahmedabad'],
                'Guney Hindistan' => ['Bangalore', 'Chennai', 'Hyderabad'],
            ],
        ],
        'Pakistan' => [
            'regions' => [
                'Pencap' => ['Lahor', 'Rawalpindi', 'Faisalabad'],
                'Sindh' => ['Karachi', 'Hyderabad', 'Sukkur'],
                'Khyber Pakhtunkhwa' => ['Islamabad', 'Peshawar', 'Abbottabad'],
            ],
        ],
        'Japonya' => [
            'regions' => [
                'Kanto' => ['Tokyo', 'Yokohama', 'Saitama'],
                'Kansai' => ['Osaka', 'Kyoto', 'Kobe'],
                'Kyushu ve Hokkaido' => ['Fukuoka', 'Sapporo', 'Nagasaki'],
            ],
        ],
        'Guney Kore' => [
            'regions' => [
                'Seul Bolgesi' => ['Seul', 'Incheon', 'Suwon'],
                'Guney Kore' => ['Busan', 'Daegu', 'Ulsan'],
                'Orta Kore' => ['Daejeon', 'Gwangju', 'Jeonju'],
            ],
        ],
    ],
];

