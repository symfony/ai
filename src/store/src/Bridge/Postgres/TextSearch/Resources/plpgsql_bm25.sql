
/*

  plpgsql_bm25.sql
  BM25 search implemented in PL/pgSQL
  version 1.1.0 by András Jankovics  https://github.com/jankovicsandras  andras@jankovics.net
  2025-04-02

  Example usage:
  SELECT bm25createindex( tablename, columnname );  -- tablename and columnname are TEXT types
  SELECT * FROM bm25topk( tablename, columnname, question, k ); -- question is TEXT, k is INTEGER

  Please note: bm25createindex will (re)create new tables <tablename>_bm25i_docs and <tablename>_bm25i_words,
  document results will come from <tablename>_bm25i_docs, not the original <tablename>. The algorithm can't
  track changes, so if documents change in the original <tablename>, then bm25createindex() must be called again.

  More information about the algorithms:
    https://github.com/dorianbrown/rank_bm25/issues/43
    Which BM25 Do You Mean? A Large-Scale Reproducibility Study of Scoring Variants by Kamphuis et al. https://cs.uwaterloo.ca/~jimmylin/publications/Kamphuis_etal_ECIR2020_preprint.pdf

  License: The Unlicense / PUBLIC DOMAIN

  Additional info for stopwordfilter() :

    Language codes follow https://en.wikipedia.org/wiki/IETF_language_tag

    Adapted from
    https://snowballstem.org/algorithms/<language>/stop.txt

    https://raw.githubusercontent.com/snowballstem/snowball/master/COPYING

    Copyright (c) 2001, Dr Martin Porter
    Copyright (c) 2004,2005, Richard Boulton
    Copyright (c) 2013, Yoshiki Shibukawa
    Copyright (c) 2006,2007,2009,2010,2011,2014-2019, Olly Betts
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions
    are met:

      1. Redistributions of source code must retain the above copyright notice,
        this list of conditions and the following disclaimer.
      2. Redistributions in binary form must reproduce the above copyright notice,
        this list of conditions and the following disclaimer in the documentation
        and/or other materials provided with the distribution.
      3. Neither the name of the Snowball project nor the names of its contributors
        may be used to endorse or promote products derived from this software
        without specific prior written permission.

    THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
    ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
    WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
    DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
    ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
    (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
    LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
    ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
    (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
    SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/


/* stopwordfilter() */
DROP FUNCTION IF EXISTS stopwordfilter;
CREATE OR REPLACE FUNCTION stopwordfilter(words TEXT[], language TEXT DEFAULT '') RETURNS TEXT[]
LANGUAGE plpgsql
AS $$
DECLARE
  w TEXT;
  w2 TEXT;
  words2 TEXT[];
  stopwords TEXT[];
  stopwords_en TEXT[] := '{"i", "me", "my", "myself", "we", "our", "ours", "ourselves", "you", "your", "yours", "yourself", "yourselves", "he", "him", "his", "himself", "she", "her", "hers", "herself", "it", "its", "itself", "they", "them", "their", "theirs", "themselves", "what", "which", "who", "whom", "this", "that", "these", "those", "am", "is", "are", "was", "were", "be", "been", "being", "have", "has", "had", "having", "do", "does", "did", "doing", "would", "should", "could", "ought", "i''m", "you''re", "he''s", "she''s", "it''s", "we''re", "they''re", "i''ve", "you''ve", "we''ve", "they''ve", "i''d", "you''d", "he''d", "she''d", "we''d", "they''d", "i''ll", "you''ll", "he''ll", "she''ll", "we''ll", "they''ll", "isn''t", "aren''t", "wasn''t", "weren''t", "hasn''t", "haven''t", "hadn''t", "doesn''t", "don''t", "didn''t", "won''t", "wouldn''t", "shan''t", "shouldn''t", "can''t", "cannot", "couldn''t", "mustn''t", "let''s", "that''s", "who''s", "what''s", "here''s", "there''s", "when''s", "where''s", "why''s", "how''s", "a", "an", "the", "and", "but", "if", "or", "because", "as", "until", "while", "of", "at", "by", "for", "with", "about", "against", "between", "into", "through", "during", "before", "after", "above", "below", "to", "from", "up", "down", "in", "out", "on", "off", "over", "under", "again", "further", "then", "once", "here", "there", "when", "where", "why", "how", "all", "any", "both", "each", "few", "more", "most", "other", "some", "such", "no", "nor", "not", "only", "own", "same", "so", "than", "too", "very"}';
  stopwords_fr TEXT[] := '{"au", "aux", "avec", "ce", "ces", "dans", "de", "des", "du", "elle", "en", "et", "eux", "il", "je", "la", "le", "leur", "lui", "ma", "mais", "me", "même", "mes", "moi", "mon", "ne", "nos", "notre", "nous", "on", "ou", "par", "pas", "pour", "qu", "que", "qui", "sa", "se", "ses", "sur", "ta", "te", "tes", "toi", "ton", "tu", "un", "une", "vos", "votre", "vous", "c", "d", "j", "l", "à", "m", "n", "s", "t", "y", "étée", "étées", "étant", "suis", "es", "êtes", "sont", "serai", "seras", "sera", "serons", "serez", "seront", "serais", "serait", "serions", "seriez", "seraient", "étais", "était", "étions", "étiez", "étaient", "fus", "fut", "fûmes", "fûtes", "furent", "sois", "soit", "soyons", "soyez", "soient", "fusse", "fusses", "fussions", "fussiez", "fussent", "ayant", "eu", "eue", "eues", "eus", "ai", "avons", "avez", "ont", "aurai", "aurons", "aurez", "auront", "aurais", "aurait", "aurions", "auriez", "auraient", "avais", "avait", "aviez", "avaient", "eut", "eûmes", "eûtes", "eurent", "aie", "aies", "ait", "ayons", "ayez", "aient", "eusse", "eusses", "eût", "eussions", "eussiez", "eussent", "ceci", "cela", "celà", "cet", "cette", "ici", "ils", "les", "leurs", "quel", "quels", "quelle", "quelles", "sans", "soi"}';
  stopwords_es TEXT[] := '{"de", "la", "que", "el", "en", "y", "a", "los", "del", "se", "las", "por", "un", "para", "con", "no", "una", "su", "al", "lo", "como", "más", "pero", "sus", "le", "ya", "o", "este", "sí", "porque", "esta", "entre", "cuando", "muy", "sin", "sobre", "también", "me", "hasta", "hay", "donde", "quien", "desde", "todo", "nos", "durante", "todos", "uno", "les", "ni", "contra", "otros", "ese", "eso", "ante", "ellos", "e", "esto", "mí", "antes", "algunos", "qué", "unos", "yo", "otro", "otras", "otra", "él", "tanto", "esa", "estos", "mucho", "quienes", "nada", "muchos", "cual", "poco", "ella", "estar", "estas", "algunas", "algo", "nosotros", "mi", "mis", "tú", "te", "ti", "tu", "tus", "ellas", "nosotras", "vosotros", "vosotras", "os", "mío", "mía", "míos", "mías", "tuyo", "tuya", "tuyos", "tuyas", "suyo", "suya", "suyos", "suyas", "nuestro", "nuestra", "nuestros", "nuestras", "vuestro", "vuestra", "vuestros", "vuestras", "esos", "esas", "estoy", "estás", "está", "estamos", "estáis", "están", "esté", "estés", "estemos", "estéis", "estén", "estaré", "estarás", "estará", "estaremos", "estaréis", "estarán", "estaría", "estarías", "estaríamos", "estaríais", "estarían", "estaba", "estabas", "estábamos", "estabais", "estaban", "estuve", "estuviste", "estuvo", "estuvimos", "estuvisteis", "estuvieron", "estuviera", "estuvieras", "estuviéramos", "estuvierais", "estuvieran", "estuviese", "estuvieses", "estuviésemos", "estuvieseis", "estuviesen", "estando", "estado", "estada", "estados", "estadas", "estad", "he", "has", "ha", "hemos", "habéis", "han", "haya", "hayas", "hayamos", "hayáis", "hayan", "habré", "habrás", "habrá", "habremos", "habréis", "habrán", "habría", "habrías", "habríamos", "habríais", "habrían", "había", "habías", "habíamos", "habíais", "habían", "hube", "hubiste", "hubo", "hubimos", "hubisteis", "hubieron", "hubiera", "hubieras", "hubiéramos", "hubierais", "hubieran", "hubiese", "hubieses", "hubiésemos", "hubieseis", "hubiesen", "habiendo", "habido", "habida", "habidos", "habidas", "soy", "eres", "es", "somos", "sois", "son", "sea", "seas", "seamos", "seáis", "sean", "seré", "serás", "será", "seremos", "seréis", "serán", "sería", "serías", "seríamos", "seríais", "serían", "era", "eras", "éramos", "erais", "eran", "fui", "fuiste", "fue", "fuimos", "fuisteis", "fueron", "fuera", "fueras", "fuéramos", "fuerais", "fueran", "fuese", "fueses", "fuésemos", "fueseis", "fuesen", "siendo", "sido", "tengo", "tienes", "tiene", "tenemos", "tenéis", "tienen", "tenga", "tengas", "tengamos", "tengáis", "tengan", "tendré", "tendrás", "tendrá", "tendremos", "tendréis", "tendrán", "tendría", "tendrías", "tendríamos", "tendríais", "tendrían", "tenía", "tenías", "teníamos", "teníais", "tenían", "tuve", "tuviste", "tuvo", "tuvimos", "tuvisteis", "tuvieron", "tuviera", "tuvieras", "tuviéramos", "tuvierais", "tuvieran", "tuviese", "tuvieses", "tuviésemos", "tuvieseis", "tuviesen", "teniendo", "tenido", "tenida", "tenidos", "tenidas", "tened"}';
  stopwords_pt TEXT[] := '{"de", "a", "o", "que", "e", "do", "da", "em", "um", "para", "com", "não", "uma", "os", "no", "se", "na", "por", "mais", "as", "dos", "como", "mas", "ao", "ele", "das", "à", "seu", "sua", "ou", "quando", "muito", "nos", "já", "eu", "também", "só", "pelo", "pela", "até", "isso", "ela", "entre", "depois", "sem", "mesmo", "aos", "seus", "quem", "nas", "me", "esse", "eles", "você", "essa", "num", "nem", "suas", "meu", "às", "minha", "numa", "pelos", "elas", "qual", "nós", "lhe", "deles", "essas", "esses", "pelas", "este", "dele", "tu", "te", "vocês", "vos", "lhes", "meus", "minhas", "teu", "tua", "teus", "tuas", "nosso", "nossa", "nossos", "nossas", "dela", "delas", "esta", "estes", "estas", "aquele", "aquela", "aqueles", "aquelas", "isto", "aquilo", "estou", "está", "estamos", "estão", "estive", "esteve", "estivemos", "estiveram", "estava", "estávamos", "estavam", "estivera", "estivéramos", "esteja", "estejamos", "estejam", "estivesse", "estivéssemos", "estivessem", "estiver", "estivermos", "estiverem", "hei", "há", "havemos", "hão", "houve", "houvemos", "houveram", "houvera", "houvéramos", "haja", "hajamos", "hajam", "houvesse", "houvéssemos", "houvessem", "houver", "houvermos", "houverem", "houverei", "houverá", "houveremos", "houverão", "houveria", "houveríamos", "houveriam", "sou", "somos", "são", "era", "éramos", "eram", "fui", "foi", "fomos", "foram", "fora", "fôramos", "seja", "sejamos", "sejam", "fosse", "fôssemos", "fossem", "for", "formos", "forem", "serei", "será", "seremos", "serão", "seria", "seríamos", "seriam", "tenho", "tem", "temos", "tém", "tinha", "tínhamos", "tinham", "tive", "teve", "tivemos", "tiveram", "tivera", "tivéramos", "tenha", "tenhamos", "tenham", "tivesse", "tivéssemos", "tivessem", "tiver", "tivermos", "tiverem", "terei", "terá", "teremos", "terão", "teria", "teríamos", "teriam"}';
  stopwords_it TEXT[] := '{"ad", "al", "allo", "ai", "agli", "all", "agl", "alla", "alle", "con", "col", "coi", "da", "dal", "dallo", "dai", "dagli", "dall", "dagl", "dalla", "dalle", "di", "del", "dello", "dei", "degli", "dell", "degl", "della", "delle", "in", "nel", "nello", "nei", "negli", "nell", "negl", "nella", "nelle", "su", "sul", "sullo", "sui", "sugli", "sull", "sugl", "sulla", "sulle", "per", "tra", "contro", "io", "tu", "lui", "lei", "noi", "voi", "loro", "mio", "mia", "miei", "mie", "tuo", "tua", "tuoi", "tue", "suo", "sua", "suoi", "sue", "nostro", "nostra", "nostri", "nostre", "vostro", "vostra", "vostri", "vostre", "mi", "ti", "ci", "vi", "lo", "la", "li", "le", "gli", "ne", "il", "un", "uno", "una", "ma", "ed", "se", "perché", "anche", "come", "dov", "dove", "che", "chi", "cui", "non", "più", "quale", "quanto", "quanti", "quanta", "quante", "quello", "quelli", "quella", "quelle", "questo", "questi", "questa", "queste", "si", "tutto", "tutti", "a", "c", "e", "i", "l", "o", "ho", "hai", "ha", "abbiamo", "avete", "hanno", "abbia", "abbiate", "abbiano", "avrò", "avrai", "avrà", "avremo", "avrete", "avranno", "avrei", "avresti", "avrebbe", "avremmo", "avreste", "avrebbero", "avevo", "avevi", "aveva", "avevamo", "avevate", "avevano", "ebbi", "avesti", "ebbe", "avemmo", "aveste", "ebbero", "avessi", "avesse", "avessimo", "avessero", "avendo", "avuto", "avuta", "avuti", "avute", "sono", "sei", "è", "siamo", "siete", "sia", "siate", "siano", "sarò", "sarai", "sarà", "saremo", "sarete", "saranno", "sarei", "saresti", "sarebbe", "saremmo", "sareste", "sarebbero", "ero", "eri", "era", "eravamo", "eravate", "erano", "fui", "fosti", "fu", "fummo", "foste", "furono", "fossi", "fosse", "fossimo", "fossero", "essendo", "faccio", "fai", "facciamo", "fanno", "faccia", "facciate", "facciano", "farò", "farai", "farà", "faremo", "farete", "faranno", "farei", "faresti", "farebbe", "faremmo", "fareste", "farebbero", "facevo", "facevi", "faceva", "facevamo", "facevate", "facevano", "feci", "facesti", "fece", "facemmo", "faceste", "fecero", "facessi", "facesse", "facessimo", "facessero", "facendo", "sto", "stai", "sta", "stiamo", "stanno", "stia", "stiate", "stiano", "starò", "starai", "starà", "staremo", "starete", "staranno", "starei", "staresti", "starebbe", "staremmo", "stareste", "starebbero", "stavo", "stavi", "stava", "stavamo", "stavate", "stavano", "stetti", "stesti", "stette", "stemmo", "steste", "stettero", "stessi", "stesse", "stessimo", "stessero", "stando"}';
  stopwords_de TEXT[] := '{"aber", "alle", "allem", "allen", "aller", "alles", "als", "also", "am", "an", "ander", "andere", "anderem", "anderen", "anderer", "anderes", "anderm", "andern", "anderr", "anders", "auch", "auf", "aus", "bei", "bin", "bis", "bist", "da", "damit", "dann", "der", "den", "des", "dem", "die", "das", "daß", "derselbe", "derselben", "denselben", "desselben", "demselben", "dieselbe", "dieselben", "dasselbe", "dazu", "dein", "deine", "deinem", "deinen", "deiner", "deines", "denn", "derer", "dessen", "dich", "dir", "du", "dies", "diese", "diesem", "diesen", "dieser", "dieses", "doch", "dort", "durch", "ein", "eine", "einem", "einen", "einer", "eines", "einig", "einige", "einigem", "einigen", "einiger", "einiges", "einmal", "er", "ihn", "ihm", "es", "etwas", "euer", "eure", "eurem", "euren", "eurer", "eures", "für", "gegen", "gewesen", "hab", "habe", "haben", "hat", "hatte", "hatten", "hier", "hin", "hinter", "ich", "mich", "mir", "ihr", "ihre", "ihrem", "ihren", "ihrer", "ihres", "euch", "im", "in", "indem", "ins", "ist", "jede", "jedem", "jeden", "jeder", "jedes", "jene", "jenem", "jenen", "jener", "jenes", "jetzt", "kann", "kein", "keine", "keinem", "keinen", "keiner", "keines", "können", "könnte", "machen", "man", "manche", "manchem", "manchen", "mancher", "manches", "mein", "meine", "meinem", "meinen", "meiner", "meines", "mit", "muss", "musste", "nach", "nicht", "nichts", "noch", "nun", "nur", "ob", "oder", "ohne", "sehr", "sein", "seine", "seinem", "seinen", "seiner", "seines", "selbst", "sich", "sie", "ihnen", "sind", "so", "solche", "solchem", "solchen", "solcher", "solches", "soll", "sollte", "sondern", "sonst", "über", "um", "und", "uns", "unse", "unsem", "unsen", "unser", "unses", "unter", "viel", "vom", "von", "vor", "während", "war", "waren", "warst", "was", "weg", "weil", "weiter", "welche", "welchem", "welchen", "welcher", "welches", "wenn", "werde", "werden", "wie", "wieder", "will", "wir", "wird", "wirst", "wo", "wollen", "wollte", "würde", "würden", "zu", "zum", "zur", "zwar", "zwischen"}';
  stopwords_nl TEXT[] := '{"de", "en", "van", "ik", "te", "dat", "die", "in", "een", "hij", "het", "niet", "zijn", "is", "was", "op", "aan", "met", "als", "voor", "had", "er", "maar", "om", "hem", "dan", "zou", "of", "wat", "mijn", "men", "dit", "zo", "door", "over", "ze", "zich", "bij", "ook", "tot", "je", "mij", "uit", "der", "daar", "haar", "naar", "heb", "hoe", "heeft", "hebben", "deze", "u", "want", "nog", "zal", "me", "zij", "nu", "ge", "geen", "omdat", "iets", "worden", "toch", "al", "waren", "veel", "meer", "doen", "toen", "moet", "ben", "zonder", "kan", "hun", "dus", "alles", "onder", "ja", "eens", "hier", "wie", "werd", "altijd", "doch", "wordt", "wezen", "kunnen", "ons", "zelf", "tegen", "na", "reeds", "wil", "kon", "niets", "uw", "iemand", "geweest", "andere"}';
  stopwords_sv TEXT[] := '{"och", "det", "att", "i", "en", "jag", "hon", "som", "han", "på", "den", "med", "var", "sig", "för", "så", "till", "är", "men", "ett", "om", "hade", "de", "av", "icke", "mig", "du", "henne", "då", "sin", "nu", "har", "inte", "hans", "honom", "skulle", "hennes", "där", "min", "man", "ej", "vid", "kunde", "något", "från", "ut", "när", "efter", "upp", "vi", "dem", "vara", "vad", "över", "än", "dig", "kan", "sina", "här", "ha", "mot", "alla", "under", "någon", "eller", "allt", "mycket", "sedan", "ju", "denna", "själv", "detta", "åt", "utan", "varit", "hur", "ingen", "mitt", "ni", "bli", "blev", "oss", "din", "dessa", "några", "deras", "blir", "mina", "samma", "vilken", "er", "sådan", "vår", "blivit", "dess", "inom", "mellan", "sådant", "varför", "varje", "vilka", "ditt", "vem", "vilket", "sitt", "sådana", "vart", "dina", "vars", "vårt", "våra", "ert", "era", "vilkas"}';
  stopwords_no TEXT[] := '{"og", "i", "jeg", "det", "at", "en", "et", "den", "til", "er", "som", "på", "de", "med", "han", "av", "ikke", "ikkje", "der", "så", "var", "meg", "seg", "men", "ett", "har", "om", "vi", "min", "mitt", "ha", "hadde", "hun", "nå", "over", "da", "ved", "fra", "du", "ut", "sin", "dem", "oss", "opp", "man", "kan", "hans", "hvor", "eller", "hva", "skal", "selv", "sjøl", "her", "alle", "vil", "bli", "ble", "blei", "blitt", "kunne", "inn", "når", "være", "kom", "noen", "noe", "ville", "dere", "deres", "kun", "ja", "etter", "ned", "skulle", "denne", "for", "deg", "si", "sine", "sitt", "mot", "å", "meget", "hvorfor", "dette", "disse", "uten", "hvordan", "ingen", "din", "ditt", "blir", "samme", "hvilken", "hvilke", "sånn", "inni", "mellom", "vår", "hver", "hvem", "vors", "hvis", "både", "bare", "enn", "fordi", "før", "mange", "også", "slik", "vært", "båe", "begge", "siden", "dykk", "dykkar", "dei", "deira", "deires", "deim", "di", "då", "eg", "ein", "eit", "eitt", "elles", "honom", "hjå", "ho", "hoe", "henne", "hennar", "hennes", "hoss", "hossen", "ingi", "inkje", "korleis", "korso", "kva", "kvar", "kvarhelst", "kven", "kvi", "kvifor", "me", "medan", "mi", "mine", "mykje", "no", "nokon", "noka", "nokor", "noko", "nokre", "sia", "sidan", "so", "somt", "somme", "um", "upp", "vere", "vore", "verte", "vort", "varte", "vart"}';
  stopwords_da TEXT[] := '{"og", "i", "jeg", "det", "at", "en", "den", "til", "er", "som", "på", "de", "med", "han", "af", "for", "ikke", "der", "var", "mig", "sig", "men", "et", "har", "om", "vi", "min", "havde", "ham", "hun", "nu", "over", "da", "fra", "du", "ud", "sin", "dem", "os", "op", "man", "hans", "hvor", "eller", "hvad", "skal", "selv", "her", "alle", "vil", "blev", "kunne", "ind", "når", "være", "dog", "noget", "ville", "jo", "deres", "efter", "ned", "skulle", "denne", "end", "dette", "mit", "også", "under", "have", "dig", "anden", "hende", "mine", "alt", "meget", "sit", "sine", "vor", "mod", "disse", "hvis", "din", "nogle", "hos", "blive", "mange", "ad", "bliver", "hendes", "været", "thi", "jer", "sådan"}';
  stopwords_ru TEXT[] := '{"и", "в", "во", "не", "что", "он", "на", "я", "с", "со", "как", "а", "то", "все", "она", "так", "его", "но", "да", "ты", "к", "у", "же", "вы", "за", "бы", "по", "только", "ее", "мне", "было", "вот", "от", "меня", "еще", "нет", "о", "из", "ему", "теперь", "когда", "даже", "ну", "вдруг", "ли", "если", "уже", "или", "ни", "быть", "был", "него", "до", "вас", "нибудь", "опять", "уж", "вам", "сказал", "ведь", "там", "потом", "себя", "ничего", "ей", "может", "они", "тут", "где", "есть", "надо", "ней", "для", "мы", "тебя", "их", "чем", "была", "сам", "чтоб", "без", "будто", "человек", "чего", "раз", "тоже", "себе", "под", "жизнь", "будет", "ж", "тогда", "кто", "этот", "говорил", "того", "потому", "этого", "какой", "совсем", "ним", "здесь", "этом", "один", "почти", "мой", "тем", "чтобы", "нее", "кажется", "сейчас", "были", "куда", "зачем", "сказать", "всех", "никогда", "сегодня", "можно", "при", "наконец", "два", "об", "другой", "хоть", "после", "над", "больше", "тот", "через", "эти", "нас", "про", "всего", "них", "какая", "много", "разве", "сказала", "три", "эту", "моя", "впрочем", "хорошо", "свою", "этой", "перед", "иногда", "лучше", "чуть", "том", "нельзя", "такой", "им", "более", "всегда", "конечно", "всю", "между"}';
  stopwords_fi TEXT[] := '{"olla", "olen", "olet", "on", "olemme", "olette", "ovat", "ole", "oli", "olisi", "olisit", "olisin", "olisimme", "olisitte", "olisivat", "olit", "olin", "olimme", "olitte", "olivat", "ollut", "olleet", "en", "et", "ei", "emme", "ette", "eivät", "minä", "minun", "minut", "minua", "minussa", "minusta", "minuun", "minulla", "minulta", "minulle", "sinä", "sinun", "sinut", "sinua", "sinussa", "sinusta", "sinuun", "sinulla", "sinulta", "sinulle", "hän", "hänen", "hänet", "häntä", "hänessä", "hänestä", "häneen", "hänellä", "häneltä", "hänelle", "me", "meidän", "meidät", "meitä", "meissä", "meistä", "meihin", "meillä", "meiltä", "meille", "te", "teidän", "teidät", "teitä", "teissä", "teistä", "teihin", "teillä", "teiltä", "teille", "he", "heidän", "heidät", "heitä", "heissä", "heistä", "heihin", "heillä", "heiltä", "heille", "tämä", "tämän", "tätä", "tässä", "tästä", "tähän", "tällä", "tältä", "tälle", "tänä", "täksi", "tuo", "tuon", "tuota", "tuossa", "tuosta", "tuohon", "tuolla", "tuolta", "tuolle", "tuona", "tuoksi", "se", "sen", "sitä", "siinä", "siitä", "siihen", "sillä", "siltä", "sille", "sinä", "siksi", "nämä", "näiden", "näitä", "näissä", "näistä", "näihin", "näillä", "näiltä", "näille", "näinä", "näiksi", "nuo", "noiden", "noita", "noissa", "noista", "noihin", "noilla", "noilta", "noille", "noina", "noiksi", "ne", "niiden", "niitä", "niissä", "niistä", "niihin", "niillä", "niiltä", "niille", "niinä", "niiksi", "kuka", "kenen", "kenet", "ketä", "kenessä", "kenestä", "keneen", "kenellä", "keneltä", "kenelle", "kenenä", "keneksi", "ketkä", "keiden", "ketkä", "keitä", "keissä", "keistä", "keihin", "keillä", "keiltä", "keille", "keinä", "keiksi", "mikä", "minkä", "minkä", "mitä", "missä", "mistä", "mihin", "millä", "miltä", "mille", "minä", "miksi", "mitkä", "joka", "jonka", "jota", "jossa", "josta", "johon", "jolla", "jolta", "jolle", "jona", "joksi", "jotka", "joiden", "joita", "joissa", "joista", "joihin", "joilla", "joilta", "joille", "joina", "joiksi", "että", "ja", "jos", "koska", "kuin", "mutta", "niin", "sekä", "sillä", "tai", "vaan", "vai", "vaikka", "kanssa", "mukaan", "noin", "poikki", "yli", "kun", "nyt", "itse"}';
  stopwords_hu TEXT[] := '{"a", "ahogy", "ahol", "aki", "akik", "akkor", "alatt", "által", "általában", "amely", "amelyek", "amelyekben", "amelyeket", "amelyet", "amelynek", "ami", "amit", "amolyan", "amíg", "amikor", "át", "abban", "ahhoz", "annak", "arra", "arról", "az", "azok", "azon", "azt", "azzal", "azért", "aztán", "azután", "azonban", "bár", "be", "belül", "benne", "cikk", "cikkek", "cikkeket", "csak", "de", "e", "eddig", "egész", "egy", "egyes", "egyetlen", "egyéb", "egyik", "egyre", "ekkor", "el", "elég", "ellen", "elő", "először", "előtt", "első", "én", "éppen", "ebben", "ehhez", "emilyen", "ennek", "erre", "ez", "ezt", "ezek", "ezen", "ezzel", "ezért", "és", "fel", "felé", "hanem", "hiszen", "hogy", "hogyan", "igen", "így", "illetve", "ill.", "ill", "ilyen", "ilyenkor", "ison", "ismét", "itt", "jó", "jól", "jobban", "kell", "kellett", "keresztül", "keressünk", "ki", "kívül", "között", "közül", "legalább", "lehet", "lehetett", "legyen", "lenne", "lenni", "lesz", "lett", "maga", "magát", "majd", "majd", "már", "más", "másik", "meg", "még", "mellett", "mert", "mely", "melyek", "mi", "mit", "míg", "miért", "milyen", "mikor", "minden", "mindent", "mindenki", "mindig", "mint", "mintha", "mivel", "most", "nagy", "nagyobb", "nagyon", "ne", "néha", "nekem", "neki", "nem", "néhány", "nélkül", "nincs", "olyan", "ott", "össze", "ő", "ők", "őket", "pedig", "persze", "rá", "s", "saját", "sem", "semmi", "sok", "sokat", "sokkal", "számára", "szemben", "szerint", "szinte", "talán", "tehát", "teljes", "tovább", "továbbá", "több", "úgy", "ugyanis", "új", "újabb", "újra", "után", "utána", "utolsó", "vagy", "vagyis", "valaki", "valami", "valamint", "való", "vagyok", "van", "vannak", "volt", "voltam", "voltak", "voltunk", "vissza", "vele", "viszont", "volna"}';
  stopwords_ga TEXT[] := '{"a", "ach", "ag", "agus", "an", "aon", "ar", "arna", "as", "b''", "ba", "beirt", "bhúr", "caoga", "ceathair", "ceathrar", "chomh", "chtó", "chuig", "chun", "cois", "céad", "cúig", "cúigear", "d''", "daichead", "dar", "de", "deich", "deichniúr", "den", "dhá", "do", "don", "dtí", "dá", "dár", "dó", "faoi", "faoin", "faoina", "faoinár", "fara", "fiche", "gach", "gan", "go", "gur", "haon", "hocht", "i", "iad", "idir", "in", "ina", "ins", "inár", "is", "le", "leis", "lena", "lenár", "m''", "mar", "mo", "mé", "na", "nach", "naoi", "naonúr", "ná", "ní", "níor", "nó", "nócha", "ocht", "ochtar", "os", "roimh", "sa", "seacht", "seachtar", "seachtó", "seasca", "seisear", "siad", "sibh", "sinn", "sna", "sé", "sí", "tar", "thar", "thú", "triúr", "trí", "trína", "trínár", "tríocha", "tú", "um", "ár", "é", "éis", "í", "ó", "ón", "óna", "ónár"}';
  stopwords_id TEXT[] := '{"yang", "dan", "di", "dari", "ini", "pada kepada", "ada adalah", "dengan", "untuk", "dalam", "oleh", "sebagai", "juga", "ke", "atau", "tidak", "itu", "sebuah", "tersebut", "dapat", "ia", "telah", "satu", "memiliki", "mereka", "bahwa", "lebih", "karena", "seorang", "akan", "seperti", "secara", "kemudian", "beberapa", "banyak", "antara", "setelah", "yaitu", "hanya", "hingga", "serta", "sama", "dia", "tetapi", "namun", "melalui", "bisa", "sehingga", "ketika", "suatu", "sendiri", "bagi", "semua", "harus", "setiap", "maka", "maupun", "tanpa", "saja", "jika", "bukan", "belum", "sedangkan", "yakni", "meskipun", "hampir", "kita", "demikian", "daripada", "apa", "ialah", "sana", "begitu", "seseorang", "selain", "terlalu", "ataupun", "saya", "bila", "bagaimana", "tapi", "apabila", "kalau", "kami", "melainkan", "boleh", "aku", "anda", "kamu", "beliau", "kalian"}';
BEGIN
  IF language = '' THEN RETURN words; END IF;
  /*words2 = words;*/
  CASE language
    WHEN 'en' THEN stopwords = stopwords_en;
    WHEN 'fr' THEN stopwords = stopwords_fr;
    WHEN 'es' THEN stopwords = stopwords_es;
    WHEN 'pt' THEN stopwords = stopwords_pt;
    WHEN 'it' THEN stopwords = stopwords_it;
    WHEN 'de' THEN stopwords = stopwords_de;
    WHEN 'nl' THEN stopwords = stopwords_nl;
    WHEN 'sv' THEN stopwords = stopwords_sv;
    WHEN 'no' THEN stopwords = stopwords_no;
    WHEN 'nn' THEN stopwords = stopwords_no;
    WHEN 'da' THEN stopwords = stopwords_da;
    WHEN 'ru' THEN stopwords = stopwords_ru;
    WHEN 'fi' THEN stopwords = stopwords_fi;
    WHEN 'hu' THEN stopwords = stopwords_hu;
    WHEN 'ga' THEN stopwords = stopwords_ga;
    WHEN 'id' THEN stopwords = stopwords_id;
  END CASE;

  words2 = '{}';
  FOREACH w IN ARRAY words LOOP
    w2 = RTRIM( LTRIM( LOWER(w) ) );
    IF (SELECT NOT w2 = ANY(stopwords)) THEN words2 = array_append( words2, w2 ); END IF;
  END LOOP;

  RETURN words2;

END;
$$;


/* bm25simpletokenize(): split text to words on whitespace, lowercase, remove some punctiation, similar to mytokenize() */
DROP FUNCTION IF EXISTS bm25simpletokenize;
CREATE OR REPLACE FUNCTION bm25simpletokenize(txt TEXT) RETURNS TEXT[]
LANGUAGE plpgsql
AS $$
DECLARE
  w TEXT;
  w2 TEXT;
  words TEXT[];
BEGIN
  FOREACH w IN ARRAY regexp_split_to_array(LOWER(txt), '\s+') LOOP
    w2 = RTRIM( LTRIM( w, '([{<"''' ), '.?!,:;)]}>"''' );
    IF LENGTH(w2) > 0 THEN
      words = array_append( words, w2 );
    END IF;
  END LOOP;
  RETURN words;
END;
$$;


/* count_words_in_array() creates doc->words counts */
DROP FUNCTION IF EXISTS count_words_in_array;
CREATE OR REPLACE FUNCTION count_words_in_array(input_array text[]) RETURNS jsonb
LANGUAGE plpgsql
AS $$
DECLARE
    word_count jsonb := '{}';
    current_word text;
BEGIN
    FOREACH current_word IN ARRAY input_array LOOP
        IF word_count->>current_word IS NULL THEN
            word_count := jsonb_set( word_count, ARRAY[current_word], '1'::jsonb, true );
        ELSE
            word_count := jsonb_set( word_count, ARRAY[current_word], ((word_count->>current_word)::int + 1)::text::jsonb );
        END IF;
    END LOOP;
    RETURN word_count;
END;
$$;


/* get_word_docs_count() */
DROP FUNCTION IF EXISTS get_word_docs_count;
CREATE OR REPLACE FUNCTION get_word_docs_count( wordstname TEXT, wf JSONB ) RETURNS VOID
LANGUAGE plpgsql
AS $$
DECLARE
  mkey TEXT;
BEGIN
  FOR mkey IN SELECT key FROM jsonb_each_text(wf) LOOP
    EXECUTE FORMAT( 'INSERT INTO %s(word, word_docs_count) VALUES (%s, COALESCE((SELECT word_docs_count FROM %s WHERE word = %s) ,1::INTEGER ) ) ON CONFLICT (word) DO UPDATE SET word_docs_count = (%s.word_docs_count + 1)::INTEGER;', wordstname, quote_literal(mkey), wordstname, quote_literal(mkey), wordstname );
  END LOOP;
END;
$$;


/* get_wsmapobj() */
DROP FUNCTION IF EXISTS get_wsmapobj;
CREATE OR REPLACE FUNCTION get_wsmapobj( docstname TEXT, word TEXT, thisidf DOUBLE PRECISION, thisk1 DOUBLE PRECISION, thisdelta DOUBLE PRECISION, algo TEXT DEFAULT '' ) RETURNS DOUBLE PRECISION[]
LANGUAGE plpgsql
AS $$
DECLARE
  res DOUBLE PRECISION[];
BEGIN

  /* default */
  IF algo = '' THEN
    algo = 'okapi';
  END IF;

  /* rank_bm25 BM25Okapi compatible */
  IF algo = 'okapi' THEN
    /* # self.wsmap[word][i] = thiswordidf * ( word_freqs[i] * (self.k1 + 1) / ( word_freqs[i] + self.hds[i] ) ) */
    EXECUTE FORMAT( 'SELECT ARRAY_AGG( %s * COALESCE(word_freqs->>%s,%s)::INTEGER * %s / ( COALESCE(word_freqs->>%s,%s)::INTEGER + ( %s * hds ) ) ORDER BY id) FROM %s;',
      thisidf, quote_literal(word), quote_literal(0), (thisk1+1), quote_literal(word), quote_literal(0), thisk1, docstname ) INTO res;
    RETURN res;
  END IF;

  /* rank_bm25 BM25L compatible */
  IF algo = 'l' THEN
    /* # self.wsmap[word][i] = self.idf[word] * twf * (self.k1 + 1) * ( twf/self.hds[di] + self.delta) / (self.k1 + twf/self.hds[di] + self.delta) */
    EXECUTE FORMAT( 'SELECT ARRAY_AGG( %s * COALESCE(word_freqs->>%s,%s)::INTEGER * %s * ( COALESCE(word_freqs->>%s,%s)::INTEGER / hds + %s ) / ( %s + COALESCE(word_freqs->>%s,%s)::INTEGER / hds + %s ) ORDER BY id) FROM %s;',
      thisidf, quote_literal(word), quote_literal(0), (thisk1+1), quote_literal(word), quote_literal(0), thisdelta, thisk1, quote_literal(word), quote_literal(0), thisdelta, docstname ) INTO res;
    RETURN res;
  END IF;

  /* rank_bm25 BM25Plus compatible */
  IF algo = 'plus' THEN
    /* # self.wsmap[word][i] = self.idf[word] * (self.delta + ( twf * (self.k1 + 1) / ( twf + self.k1 * self.hds[di] ) )) */
    EXECUTE FORMAT( 'SELECT ARRAY_AGG( %s * ( %s + ( COALESCE(word_freqs->>%s,%s)::INTEGER * %s / ( COALESCE(word_freqs->>%s,%s)::INTEGER + %s * hds ) ) ) ORDER BY id) FROM %s;',
      thisidf, thisdelta, quote_literal(word), quote_literal(0), (thisk1+1), quote_literal(word), quote_literal(0), thisk1, docstname ) INTO res;
    RETURN res;
  END IF;


  /* Robertson et al. */
  IF algo = 'robertson' THEN
    EXECUTE FORMAT( 'SELECT ARRAY_AGG( %s * COALESCE(word_freqs->>%s,%s)::INTEGER / ( COALESCE(word_freqs->>%s,%s)::INTEGER + ( %s * hds ) ) ORDER BY id) FROM %s;',
      thisidf, quote_literal(word), quote_literal(0), quote_literal(word), quote_literal(0), thisk1, docstname ) INTO res;
    RETURN res;
  END IF;

  /* Lucene(accurate) */
  IF algo = 'luceneaccurate' THEN
    EXECUTE FORMAT( 'SELECT ARRAY_AGG( %s * COALESCE(word_freqs->>%s,%s)::INTEGER / ( COALESCE(word_freqs->>%s,%s)::INTEGER + ( %s * hds ) ) ORDER BY id) FROM %s;',
      thisidf, quote_literal(word), quote_literal(0), quote_literal(word), quote_literal(0), thisk1, docstname ) INTO res;
    RETURN res;
  END IF;

  /* ATIRE */
  IF algo = 'atire' THEN
    EXECUTE FORMAT( 'SELECT ARRAY_AGG( %s * COALESCE(word_freqs->>%s,%s)::INTEGER * %s / ( COALESCE(word_freqs->>%s,%s)::INTEGER + ( %s * hds ) ) ORDER BY id) FROM %s;',
      thisidf, quote_literal(word), quote_literal(0), (thisk1+1), quote_literal(word), quote_literal(0), thisk1, docstname ) INTO res;
    RETURN res;
  END IF;

  /* BM25L */
  IF algo = 'bm25l' THEN
    EXECUTE FORMAT( 'SELECT ARRAY_AGG( %s * COALESCE(word_freqs->>%s,%s)::INTEGER * %s * ( COALESCE(word_freqs->>%s,%s)::INTEGER / hds + %s ) / ( %s + COALESCE(word_freqs->>%s,%s)::INTEGER / hds + %s ) ORDER BY id) FROM %s;',
      thisidf, quote_literal(word), quote_literal(0), (thisk1+1), quote_literal(word), quote_literal(0), thisdelta, thisk1, quote_literal(word), quote_literal(0), thisdelta, docstname ) INTO res;
    RETURN res;
  END IF;

  /* BM25+ */
  IF algo = 'bm25plus' THEN
    EXECUTE FORMAT( 'SELECT ARRAY_AGG( %s * ( %s + ( COALESCE(word_freqs->>%s,%s)::INTEGER * %s / ( COALESCE(word_freqs->>%s,%s)::INTEGER + %s * hds ) ) ) ORDER BY id) FROM %s;',
      thisidf, thisdelta, quote_literal(word), quote_literal(0), (thisk1+1), quote_literal(word), quote_literal(0), thisk1, docstname ) INTO res;
    RETURN res;
  END IF;

END;
$$;


/* bm25createindex() */
DROP FUNCTION IF EXISTS bm25createindex;
CREATE OR REPLACE FUNCTION bm25createindex(tablename TEXT, columnname TEXT, algo TEXT DEFAULT '', stopwordslanguage TEXT DEFAULT '') RETURNS VOID
LANGUAGE plpgsql
AS $$
DECLARE
  docstname TEXT := tablename || '_' ||  columnname || '_bm25i_docs' || algo;
  wordstname TEXT := tablename || '_' ||  columnname || '_bm25i_words' || algo;
  paramstname TEXT := tablename || '_' ||  columnname || '_bm25i_params' || algo;
  param_k1 DOUBLE PRECISION := 1.5;
  param_b DOUBLE PRECISION := 0.75;
  param_epsilon DOUBLE PRECISION := 0.25;
  param_delta DOUBLE PRECISION := 1.0;
  corpus_len INTEGER := 0;
  vocab_len INTEGER := 0;
  total_word_count INTEGER := 0;
  avg_doc_len DOUBLE PRECISION := 0;
  idf_sum DOUBLE PRECISION := 0;
  average_idf DOUBLE PRECISION := 0;
  param_eps DOUBLE PRECISION := 0;
BEGIN

  /* default */
  IF algo = '' THEN
    algo = 'okapi';
  END IF;

  /* BM25L and BM25Plus parameters */
  /* rank_bm25 BM25L compatible */
  IF algo = 'l' THEN
    param_k1 := 1.5;
    param_b := 0.75;
    param_delta := 0.5;
    param_epsilon := 0.25;
  END IF;

  /* rank_bm25 BM25Plus compatible */
  IF algo = 'plus' THEN
    param_k1 := 1.5;
    param_b := 0.75;
    param_delta := 1;
    param_epsilon := 0.25;
  END IF;

  /* create _bm25i_params table */
  EXECUTE FORMAT( 'DROP TABLE IF EXISTS  %s;', paramstname );
  EXECUTE FORMAT( 'CREATE TABLE %s ( paramname TEXT PRIMARY KEY, value DOUBLE PRECISION );', paramstname );
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('param_k1'), param_k1 );
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('param_b'), param_b );
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('param_epsilon'), param_epsilon );
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('param_delta'), param_delta );

  /* create docs table */
  EXECUTE FORMAT( 'DROP TABLE IF EXISTS %s;', docstname );
  EXECUTE FORMAT( 'CREATE TABLE %s (id SERIAL PRIMARY KEY, doc TEXT, tokenized_doc TEXT[]);', docstname );
  /*EXECUTE FORMAT( 'INSERT INTO %s (doc, tokenized_doc) SELECT %s AS doc, bm25simpletokenize(%s) AS tokenized_doc FROM %s ;', docstname, columnname, columnname, tablename );*/
  EXECUTE FORMAT( 'INSERT INTO %s (doc, tokenized_doc) SELECT %s AS doc, stopwordfilter( bm25simpletokenize(%s), %s ) AS tokenized_doc FROM %s ;', docstname, columnname, columnname, quote_literal(stopwordslanguage), tablename );


  /* add doc_lens */
  EXECUTE FORMAT( 'ALTER TABLE %s ADD COLUMN doc_lens INTEGER;', docstname );
  EXECUTE FORMAT( 'UPDATE %s SET doc_lens=subquery.doc_lens FROM (SELECT tokenized_doc AS td, CARDINALITY(tokenized_doc) AS doc_lens FROM %s) AS subquery WHERE tokenized_doc = subquery.td;', docstname, docstname );

  /* add word_freqs (JSONB word:count object) */
  EXECUTE FORMAT( 'ALTER TABLE %s ADD COLUMN word_freqs JSONB;', docstname );
  EXECUTE FORMAT( 'UPDATE %s SET word_freqs=count_words_in_array(tokenized_doc);', docstname );

  /* total word count */
  EXECUTE FORMAT( 'SELECT SUM(doc_lens) FROM %s;', docstname ) INTO total_word_count;
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('total_word_count'), total_word_count );

  /* create words table */
  EXECUTE FORMAT( 'DROP TABLE IF EXISTS %s;', wordstname );
  EXECUTE FORMAT( 'CREATE TABLE %s ( word TEXT PRIMARY KEY, word_docs_count INTEGER, idf DOUBLE PRECISION );', wordstname );

  /* count docs with each word */
  EXECUTE FORMAT('SELECT get_word_docs_count( %s, word_freqs ) FROM %s;', quote_literal(wordstname), docstname );

  /* self.avg_doc_len = total_word_count / self.corpus_len */
  EXECUTE FORMAT( 'SELECT COUNT(doc_lens) FROM %s WHERE doc_lens > 0;', docstname ) INTO corpus_len;
  avg_doc_len := total_word_count::DOUBLE PRECISION / corpus_len::DOUBLE PRECISION;
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('corpus_len'), corpus_len );
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('avg_doc_len'), avg_doc_len );

  /*  # precalc "half of divisor" (1 - self.b + self.b * doc_lens / self.avg_doc_len)  */
  EXECUTE FORMAT( 'ALTER TABLE %s ADD COLUMN hds DOUBLE PRECISION;', docstname );
  EXECUTE FORMAT( 'UPDATE %s SET hds = ( 1.0::DOUBLE PRECISION - %s + %s * doc_lens / %s ) ;', docstname,  param_b, param_b, avg_doc_len );

  /* rank_bm25 BM25Okapi compatible */
  IF algo = 'okapi' THEN
    EXECUTE FORMAT( 'UPDATE %s SET idf = LN( %s - word_docs_count::DOUBLE PRECISION + 0.5::DOUBLE PRECISION) - LN( word_docs_count::DOUBLE PRECISION + 0.5::DOUBLE PRECISION);', wordstname, corpus_len::DOUBLE PRECISION );
    EXECUTE FORMAT( 'SELECT SUM(idf) FROM %s;', wordstname ) INTO idf_sum;
    EXECUTE FORMAT( 'SELECT COUNT(word) FROM %s;', wordstname ) INTO vocab_len;
    average_idf = idf_sum / vocab_len::DOUBLE PRECISION;
    param_eps = param_epsilon * average_idf;
    EXECUTE FORMAT( 'UPDATE %s SET idf = %s WHERE idf < 0;', wordstname, param_eps );
  END IF;

  /* rank_bm25 BM25L compatible */
  IF algo = 'l' THEN
    EXECUTE FORMAT( 'UPDATE %s SET idf = LN( %s + 1.0::DOUBLE PRECISION ) - LN( word_docs_count::DOUBLE PRECISION + 0.5::DOUBLE PRECISION);', wordstname, corpus_len::DOUBLE PRECISION );
  END IF;

  /* rank_bm25 BM25Plus compatible */
  IF algo = 'plus' THEN
    EXECUTE FORMAT( 'UPDATE %s SET idf = LN( %s + 1.0::DOUBLE PRECISION ) - LN( word_docs_count::DOUBLE PRECISION );', wordstname, corpus_len::DOUBLE PRECISION );
  END IF;

  /* Robertson et al. */
  IF algo = 'robertson' THEN
    EXECUTE FORMAT( 'UPDATE %s SET idf = LN( ( %s - word_docs_count::DOUBLE PRECISION + 0.5::DOUBLE PRECISION) / ( word_docs_count::DOUBLE PRECISION + 0.5::DOUBLE PRECISION ) );', wordstname, corpus_len::DOUBLE PRECISION );
  END IF;

  /* Lucene(accurate) */
  IF algo = 'luceneaccurate' THEN
    EXECUTE FORMAT( 'UPDATE %s SET idf = LN( 1.0::DOUBLE PRECISION + ( ( %s - word_docs_count::DOUBLE PRECISION + 0.5::DOUBLE PRECISION) / ( word_docs_count::DOUBLE PRECISION + 0.5::DOUBLE PRECISION ) ) );', wordstname, corpus_len::DOUBLE PRECISION );
  END IF;

  /* ATIRE */
  IF algo = 'atire' THEN
    EXECUTE FORMAT( 'UPDATE %s SET idf = LN( %s / word_docs_count::DOUBLE PRECISION );', wordstname, corpus_len::DOUBLE PRECISION );
  END IF;

  /* BM25L */
  IF algo = 'bm25l' THEN
    EXECUTE FORMAT( 'UPDATE %s SET idf = LN( ( %s + 1::DOUBLE PRECISION ) / ( word_docs_count::DOUBLE PRECISION + 0.5::DOUBLE PRECISION ) );', wordstname, corpus_len::DOUBLE PRECISION );
  END IF;

  /* BM25+ */
  IF algo = 'bm25plus' THEN
    EXECUTE FORMAT( 'UPDATE %s SET idf = LN( ( %s + 1::DOUBLE PRECISION ) / word_docs_count::DOUBLE PRECISION );', wordstname, corpus_len::DOUBLE PRECISION );
  END IF;

  /* parameters */
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('idf_sum'), idf_sum );
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('vocab_len'), vocab_len );
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('average_idf'), average_idf );
  EXECUTE FORMAT( 'INSERT INTO %s(paramname,value) VALUES( %s, %s );', paramstname, quote_literal('param_eps'), param_eps );

  /*  words * documents score map  */
  EXECUTE FORMAT( 'ALTER TABLE %s ADD COLUMN wsmap DOUBLE PRECISION[];', wordstname );
  EXECUTE FORMAT( 'UPDATE %s SET wsmap = get_wsmapobj( %s, word, idf, %s, %s, %s );', wordstname, quote_literal(docstname), param_k1, param_delta, quote_literal(algo) );

END;
$$;


/* bm25scorerows() get the documentscores row for each word */
DROP FUNCTION IF EXISTS bm25scorerows;
CREATE OR REPLACE FUNCTION bm25scorerows(tablename TEXT, mquery TEXT, stopwordslanguage TEXT DEFAULT '') RETURNS SETOF double precision[]
LANGUAGE plpgsql
AS $$
DECLARE
  w TEXT := '';
BEGIN
  FOR w IN SELECT unnest( stopwordfilter( bm25simpletokenize(mquery), stopwordslanguage ) )
  LOOP
    RETURN QUERY EXECUTE FORMAT( 'SELECT wsmap FROM %s WHERE word = %s;', tablename, quote_literal(w) );
  END LOOP;
END;
$$;


/* bm25scoressum(): sums the score rows to one array with the document scores ; TODO: instead of xdocstname maybe with temp table, race condition here? */
DROP FUNCTION IF EXISTS bm25scoressum;
CREATE OR REPLACE FUNCTION bm25scoressum(tablename TEXT, mquery TEXT, stopwordslanguage TEXT DEFAULT '') RETURNS SETOF double precision[]
LANGUAGE plpgsql
AS $$
DECLARE
  xdocstname TEXT := tablename || '_bm25i_temp';
BEGIN
  EXECUTE FORMAT( 'DROP TABLE IF EXISTS %s;', xdocstname );
  EXECUTE FORMAT( 'CREATE TABLE %s AS SELECT bm25scorerows(%s, %s, %s);', xdocstname, quote_literal(tablename), quote_literal(mquery), quote_literal(stopwordslanguage) );
  RETURN QUERY EXECUTE FORMAT( 'SELECT ARRAY_AGG(sum ORDER BY ord) FROM (SELECT ord, SUM(int) FROM %s, unnest(bm25scorerows) WITH ORDINALITY u(int, ord) GROUP BY ord) AS subquery;', xdocstname ); /* Issue #4 fix: AS subquery */
END;
$$;


/* bm25scunnest(): unnests the score array */
DROP FUNCTION IF EXISTS bm25scunnest;
CREATE OR REPLACE FUNCTION bm25scunnest(tablename TEXT, mquery TEXT, stopwordslanguage TEXT DEFAULT '') RETURNS TABLE(score double precision)
LANGUAGE plpgsql
AS $$
BEGIN
  RETURN QUERY SELECT unnest( bm25scoressum( tablename, mquery, stopwordslanguage ) );
END;
$$;


/* bm25isc(): returns the index and score of the documents; index starts with 1 */
DROP FUNCTION IF EXISTS bm25isc;
CREATE OR REPLACE FUNCTION bm25isc(tablename TEXT, mquery TEXT, stopwordslanguage TEXT DEFAULT '') RETURNS TABLE(id BIGINT, score double precision)
LANGUAGE plpgsql
AS $$
BEGIN
  RETURN QUERY SELECT row_number() OVER () AS id, bm25scunnest FROM bm25scunnest( tablename, mquery, stopwordslanguage ) ;
END;
$$;


/* bm25topk(): returns the index, score and document sorted and limited |  TABLE(id INT, id2 BIGINT, score double precision, doc TEXT) */
DROP FUNCTION IF EXISTS bm25topk;
CREATE OR REPLACE FUNCTION bm25topk(tablename TEXT, columnname TEXT, mquery TEXT, k INT, algo TEXT DEFAULT '', stopwordslanguage TEXT DEFAULT '') RETURNS TABLE(id INTEGER, score double precision, doc TEXT)
LANGUAGE plpgsql
AS $$
DECLARE
  docstname TEXT := tablename || '_' ||  columnname || '_bm25i_docs' || algo;
  wordstname TEXT := tablename || '_' ||  columnname || '_bm25i_words' || algo;
BEGIN
  RETURN QUERY EXECUTE FORMAT( 'SELECT t1.id, t2.score, t1.%s AS doc FROM (SELECT id, doc AS %s FROM %s) t1 INNER JOIN ( SELECT id, score FROM bm25isc(%s,%s,%s) ) t2 ON ( t1.id = t2.id ) ORDER BY t2.score DESC LIMIT %s;', columnname, columnname, docstname, quote_literal(wordstname), quote_literal(mquery), quote_literal(stopwordslanguage), k );
END;
$$;
