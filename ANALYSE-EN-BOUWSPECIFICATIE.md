# WP Simple Events

## Geconsolideerde functionele en technische analyse

**Status:** definitieve analyse voor de eerste bouwversie\
**Laatste update:** 13 juli 2026\
**Pluginnaam:** WP Simple Events\
**Auteur:** MiMe\
**Doelplatform:** WordPress-webshop met WooCommerce en Elementor

---

## 1. Managementsamenvatting

Een eigen lichte eventplugin is voor deze website technisch en functioneel een goede keuze. Events worden als een native WordPress Custom Post Type gebouwd, met dezelfde redactionele ervaring als blogberichten en enkele aanvullende eventvelden.

De plugin moet zelfstandig functioneren:

- WordPress beheert de eventinhoud.
- WooCommerce is geen vereiste en wordt niet aangepast.
- Elementor is een optionele presentatielaag.
- Shortcodes en fallbacktemplates werken ook zonder Elementor.
- Een kalender gebruikt een begrensde publieke REST-feed.

### Definitieve scopebeslissingen

De volgende functies vallen bewust **buiten scope**:

- terugkerende of herhalende events;
- interactieve kaarten;
- geocoding en coördinaten;
- meerdere kaartmarkers of een eventkaart;
- ticketing, registraties en deelnemersbeheer;
- externe kalendersynchronisatie.

Elk event heeft in versie 1 exact één start- en eindmoment. Een event dat opnieuw plaatsvindt, wordt gedupliceerd en als zelfstandig event gepubliceerd. Een locatie bestaat uit tekstvelden en eventueel een handmatig ingevoerde externe route- of locatielink.

Deze keuze houdt het datamodel native, vermijdt een eigen occurrence-tabel, vermindert JavaScript en externe diensten en maakt de plugin veel eenvoudiger te bouwen, testen en onderhouden.

### Kern van versie 1.0

- aparte tab “Events” in wp-admin;
- blogachtige editorervaring;
- startdatum, starttijd, einddatum, eindtijd en hele-dag-event;
- locatie, adres en optionele externe link;
- eventstatus;
- eigen eventcategorieën en eventtags;
- individuele eventpagina en eventarchief;
- lijst-, raster- en kalenderweergave;
- filters op categorie en tag;
- shortcodes;
- Elementor-widgets;
- responsieve en toegankelijke output;
- SEO-geschikte eventpagina’s;
- geen automatische verwijdering van voorbije events.

---

# Deel I — Analyse als technisch expert

## 2. Basisarchitectuur

### 2.1 Custom Post Type

Events worden geregistreerd als:

```text
wpse_event
```

Aanbevolen configuratie:

```text
public: true
publicly_queryable: true
show_ui: true
show_in_menu: true
show_in_rest: true
has_archive: true
rewrite slug: events
hierarchical: false
exclude_from_search: false
supports:
  title
  editor
  excerpt
  thumbnail
  author
  revisions
  custom-fields
```

WordPress raadt aan Custom Post Types in een plugin te registreren, zodat de inhoud behouden blijft wanneer het thema verandert. Een publiek CPT krijgt bovendien de normale beheer- en permalinkfunctionaliteit. [WordPress: Registering Custom Post Types](https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/)

### 2.2 Technische identificatie

| Onderdeel | Waarde |
|---|---|
| Pluginnaam | WP Simple Events |
| Auteur | MiMe |
| Pluginmap | `wp-simple-events` |
| Hoofdbestand | `wp-simple-events.php` |
| Text domain | `wp-simple-events` |
| PHP-namespace | `MiMe\WPSimpleEvents` |
| Prefix | `wpse_` |
| Post type | `wpse_event` |
| Categorie-taxonomie | `wpse_event_category` |
| Tagtaxonomie | `wpse_event_tag` |
| Metaprefix | `_wpse_` |
| REST-namespace | `wpse/v1` |
| Thema-overridefolder | `wp-simple-events` |

De post-typekey en taxonomiekeys blijven binnen de WordPress-limieten. [WordPress `register_post_type`](https://developer.wordpress.org/reference/functions/register_post_type/)

### 2.3 Minimale en aanbevolen platformversies

### Vast voorstel

| Platform | Minimum | Ontwikkel- en testdoel |
|---|---:|---:|
| WordPress | 6.9 | 7.0.1 en de laatste stabiele release |
| PHP | 8.3 | 8.3, 8.4 en 8.5 |
| MySQL | geen extra pluginminimum | 8.0+ aanbevolen |
| MariaDB | geen extra pluginminimum | 10.11+ aanbevolen |
| WooCommerce | niet vereist | actuele 10.9.x naast de plugin testen |
| Elementor | niet vereist voor core | integratie vanaf 3.35; testen op recente 3.x en actuele 4.x |
| Elementor Pro | niet vereist | alleen nodig voor Theme Builder en bepaalde dynamic-contentflows |

### Motivatie

- WordPress 7.0.1 is sinds 9 juli 2026 de actuele stabiele release. WooCommerce 10.9.4 vereist inmiddels WordPress 6.9 of hoger. Door WordPress 6.9 als minimum te nemen, sluit de plugin aan op de actuele webshopstack zonder onnodige compatibiliteitscode voor verouderde WordPress-versies. [WordPress releases](https://wordpress.org/download/releases/) en [WooCommerce op WordPress.org](https://wordpress.org/plugins/woocommerce/)
- WordPress en Elementor bevelen PHP 8.3 aan. PHP 8.2 ontvangt nog slechts tot 31 december 2026 securityfixes, terwijl PHP 8.3 tot eind 2027 securitysupport heeft. Voor een nieuwe plugin in 2026 is PHP 8.3 daarom een betere ondergrens. [WordPress requirements](https://wordpress.org/about/requirements/), [Elementor requirements](https://wordpress.org/plugins/elementor/) en [PHP supported versions](https://www.php.net/supported-versions.php)
- De kernplugin gebruikt geen WooCommerce-API en declareert WooCommerce dus niet als dependency. Compatibiliteit wordt wel getest met de actuele WooCommerce-versie.
- De Elementorintegratie wordt later en conditioneel geladen. Bij de start van fase 4 is Elementor 4.1.5 actueel, maar veel bestaande sites draaien nog een recente 3.x-lijn. Minimum 3.35 vermijdt oude API-compatibiliteitslagen en laat een gecontroleerde overgang naar 4.x toe. Dit minimum is bij de start van fase 4 opnieuw geverifieerd.

### Pluginheaders bij versie 1.0

```text
Plugin Name: WP Simple Events
Author: MiMe
Version: 1.0.0
Requires at least: 6.9
Requires PHP: 8.3
Text Domain: wp-simple-events
License: GPL-2.0-or-later
```

`Tested up to`, WooCommerce-compatibiliteit en Elementor-compatibiliteit worden bij elke release bijgewerkt op basis van werkelijk uitgevoerde tests.

## 3. Datamodel

### 3.1 Standaard WordPress-data

| Inhoud | WordPress-opslag |
|---|---|
| Titel | `post_title` |
| Beschrijving | `post_content` |
| Samenvatting | `post_excerpt` |
| Afbeelding | featured image |
| Publicatiestatus | `post_status` |
| URL-slug | `post_name` |
| Auteur | `post_author` |
| Revisies | WordPress revisions |

De WordPress-publicatiedatum wordt niet als eventdatum gebruikt. Publicatiedatum en feitelijke eventdatum zijn twee verschillende concepten.

### 3.2 Eventmeta

| Metakey | Type | Verplicht | Functie |
|---|---:|---:|---|
| `_wpse_start_local` | string | ja | Lokale startdatum en -tijd |
| `_wpse_end_local` | string | nee | Lokale einddatum en -tijd |
| `_wpse_start_utc` | integer | afgeleid | Sortering en datumqueries |
| `_wpse_end_utc` | integer | afgeleid | Overlap- en kalenderqueries |
| `_wpse_all_day` | boolean | ja | Hele-dag-event |
| `_wpse_timezone` | string | ja | IANA-tijdzone bij opslag |
| `_wpse_venue` | string | nee | Locatie- of zaalnaam |
| `_wpse_address` | string | nee | Leesbaar adres |
| `_wpse_location_url` | string | nee | Externe route- of locatielink |
| `_wpse_event_url` | string | nee | Externe info- of inschrijvingslink |
| `_wpse_event_status` | string | ja | `scheduled`, `cancelled`, `postponed` |

Alle velden worden afzonderlijk opgeslagen. Er komt geen geserialiseerde eventdata-array en geen eigen databasetabel.

De meta wordt via `register_post_meta()` geregistreerd met:

- type;
- `single => true`;
- standaardwaarde;
- sanitization callback;
- authorization callback;
- `show_in_rest => true` waar nuttig.

Omdat geregistreerde meta via REST beschikbaar moet zijn, declareert het CPT ondersteuning voor `custom-fields`. [WordPress: Registered meta in REST](https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/)

## 4. Datum- en tijdstrategie

### 4.1 Opslag

1. De beheerder voert lokale datum en tijd in.
2. De plugin gebruikt standaard de WordPress-sitetijdzone.
3. De gebruikte IANA-tijdzone, bijvoorbeeld `Europe/Brussels`, wordt bij het event opgeslagen.
4. De lokale invoer blijft beschikbaar voor beheer.
5. Bij opslaan worden UTC-timestamps afgeleid.
6. Sortering en vergelijking gebruiken UTC.
7. Weergave gebruikt `wp_date()` in de opgeslagen eventtijdzone.

Hierdoor blijft het eventuur stabiel wanneer de algemene sitetijdzone later wordt aangepast. WordPress biedt `wp_timezone()` en gelokaliseerde formatting via `wp_date()`. [WordPress `wp_date`](https://developer.wordpress.org/reference/functions/wp_date/)

### 4.2 Validatieregels

- Een startdatum is verplicht om te publiceren.
- Een ontbrekend eindmoment wordt gelijkgesteld aan het startmoment.
- Het eindmoment mag niet vóór het startmoment liggen.
- Een hele-dag-event heeft geen zichtbare start- of eindtijd.
- Een meerdaags hele-dag-event gebruikt inclusieve datums in beheer.
- De kalenderfeed zet het einde waar nodig om naar het exclusieve eindformaat van de kalenderbibliotheek.
- Een event is actief zolang `end_utc >= nu`.

### 4.3 Lopende events

Een event dat gisteren begon en vandaag eindigt, moet nog tussen actuele/aankomende events staan. De selectie gebruikt daarom het einde en niet alleen de start:

```text
upcoming/active: end_utc >= now
past: end_utc < now
```

Sortering van actuele events gebeurt op `start_utc`, oplopend.

## 5. Taxonomieën

Gebruik afzonderlijke taxonomieën:

- `wpse_event_category`: hiërarchisch, zoals blogcategorieën;
- `wpse_event_tag`: niet-hiërarchisch, zoals blogtags.

De bestaande blogcategorieën en blogtags worden niet gedeeld. Anders mengen blog- en eventarchieven zich en kunnen filters onverwachte resultaten geven.

## 6. Backoffice

### 6.1 Eventmenu

Het hoofdmenu “Events” bevat:

- Alle events;
- Nieuw event;
- Eventcategorieën;
- Eventtags;
- Instellingen.

### 6.2 Eventeditor

Gebruik een native WordPress-metabox “Eventgegevens”. Dat blijft licht en werkt met de Block Editor en Classic Editor.

#### Datum en tijd

- Checkbox “Dit event duurt de hele dag”.
- Startdatum.
- Starttijd.
- Einddatum.
- Eindtijd.
- Niet-bewerkbare indicatie van de tijdzone.

#### Locatie

- Locatienaam.
- Adres.
- Externe route- of locatielink.

#### Status en link

- Status: gepland, geannuleerd of uitgesteld.
- Externe event- of inschrijvingslink.

Categorieën, tags, afbeelding, samenvatting en inhoud blijven normale WordPress-panelen.

### 6.3 Publicatievalidatie

Een centrale `EventValidator` wordt gebruikt door:

- wp-admin;
- REST-updates;
- eventuele latere imports;
- automatische tests.

Bij ongeldige gegevens:

- worden corrupte waarden niet opgeslagen;
- wordt publicatie tegengehouden;
- blijft het event concept;
- krijgt de beheerder een concrete foutmelding.

Client-side validatie verbetert de ervaring, maar server-side validatie is altijd bepalend.

### 6.4 Beheeroverzicht

Kolommen:

- titel;
- start;
- einde;
- hele dag;
- locatie;
- categorieën;
- eventstatus;
- publicatiestatus.

Filters:

- alle;
- aankomend/actief;
- voorbij;
- geannuleerd;
- uitgesteld;
- categorie.

Sortering:

- aankomende events standaard oplopend op start;
- voorbije events aflopend;
- sorteerbare start- en eindkolom.

### 6.5 Event dupliceren

Een actie “Dupliceer event” is waardevol wanneer hetzelfde type event opnieuw wordt georganiseerd.

Bij dupliceren:

- ontstaat een nieuw concept;
- titel krijgt optioneel “Kopie”;
- inhoud, afbeelding, categorieën, tags en locatie worden gekopieerd;
- start- en einddatum worden wel gekopieerd maar duidelijk gemarkeerd voor controle;
- externe commerciële koppelingen worden standaard niet gekopieerd.

Dit vervangt op een eenvoudige manier een deel van de behoefte aan herhalende events.

## 7. Rechten en rollen

Gebruik eigen gemapte capabilities voor events.

Bij activatie:

- administrator krijgt alle eventrechten;
- editor kan events creëren, bewerken, publiceren en verwijderen;
- WooCommerce `shop_manager` krijgt niet automatisch redactionele eventrechten.

Benodigde meta- en primitive capabilities volgen het normale WordPress-model met `map_meta_cap => true`.

## 8. Front-endtemplates

### 8.1 Individuele eventpagina

Volgorde:

1. titel;
2. uitgelichte afbeelding;
3. datum en tijd;
4. statusmelding;
5. locatie en adres;
6. optionele externe locatielink;
7. hoofdinhoud;
8. optionele externe actieknop;
9. categorieën en tags.

Lege waarden worden volledig weggelaten. Er verschijnt bijvoorbeeld nooit `Locatie: —`.

### 8.2 Eventarchief

Standaardroute:

```text
/events/
```

Standaardgedrag:

- toont actieve en toekomstige events;
- sorteert op startdatum;
- gebruikt paginering;
- heeft filters op categorie en tag;
- toont een duidelijke lege toestand;
- biedt een keuze om voorbije events te bekijken.

Voorbije events blijven gepubliceerd en via hun permalink bereikbaar. Automatisch depubliceren zou oude links, gedeelde informatie en SEO-waarde verwijderen.

### 8.3 Templateprioriteit

1. Elementor Theme Builder-template indien actief en van toepassing.
2. Theme override:
   - `your-theme/wp-simple-events/single-wpse_event.php`
   - `your-theme/wp-simple-events/archive-wpse_event.php`
3. Plugintemplate als fallback.

Template-parts:

- event card;
- event meta;
- datumblok;
- locatieblok;
- statusbadge;
- empty state.

Shortcodes, templates en Elementor gebruiken dezelfde renderer.

### 8.4 Stylingbeleid voor versie 1

De native output krijgt alleen de CSS die nodig is voor structuur, bruikbaarheid en toegankelijkheid. De plugin wordt geen tweede designsysteem naast het thema of Elementor.

De plugin erft standaard:

- `font-family`;
- normale tekstkleur;
- headingstijl waar mogelijk;
- basis line-height;
- linkstijl waar die toegankelijk blijft;
- algemene formulier- en buttonstijl waar het thema daarvoor bruikbare selectors of variabelen levert.

De plugin levert zelf:

- grid- en lijstlayout;
- consistente spacing;
- responsieve kolommen;
- beeldverhoudingen;
- datum- en statusstructuur;
- toegankelijke focusindicatoren;
- kalenderlayout en noodzakelijke FullCalendar-correcties;
- nette lege, loading- en foutstates.

Niet opnemen in de standaardstijl:

- een eigen lettertype;
- vaste merk- of accentkleuren;
- gradients;
- zware shadows;
- animaties zonder functionele waarde;
- theme resets;
- globale selectors zoals `button`, `a`, `h2` of `.container`.

De HTML gebruikt stabiele, genamespacede classes, bijvoorbeeld `wpse-event-card`, en een beperkt aantal CSS custom properties:

```css
--wpse-color-accent
--wpse-color-border
--wpse-color-muted
--wpse-spacing
--wpse-radius
--wpse-image-ratio
```

Deze variabelen krijgen neutrale, toegankelijke fallbacks en kunnen vanuit het thema, Customizer-CSS of Elementor worden overschreven. Typografie gebruikt `inherit`; plugin-CSS mag geen WooCommerce-, Elementor- of themastijlen globaal overschrijven.

### 8.5 Native templates blijven verplicht

Elementor wordt niet gebruikt als vereiste voor eventpagina’s. De plugin levert altijd een degelijke native single- en archieffallback. Dat is nodig voor:

- correcte output vóór de Elementorfase;
- werking wanneer Elementor tijdelijk uitstaat;
- themawissels;
- previews, tests en foutdiagnose;
- sites met Elementor Free zonder Theme Builder.

Wanneer Elementor Pro Theme Builder later een Single Event- of Event Archive-template met passende display conditions levert, krijgt die voorrang. Elementor ondersteunt Custom Post Types en kan via Theme Builder aparte single- en archieftemplates toepassen wanneer het CPT publiek is en `has_archive` gebruikt. [Elementor: Dynamic content and Post Types](https://elementor.com/help/intro-to-dynamic-content/) en [Single Post template](https://elementor.com/help/single-post-site-part/)

De plugin mag daarom geen agressieve `template_include`-override gebruiken die Elementor of het thema buitenspel zet. De fallbacktemplate wordt alleen gebruikt wanneer geen hoger-prioritaire theme- of buildertemplate de request afhandelt.

## 9. Shortcodes

### 9.1 Lijst of raster

```text
[wpse_events
    view="grid"
    period="upcoming"
    limit="12"
    columns="3"
    category="workshops"
    tag=""
    filters="true"
    pagination="true"
]
```

Ondersteunde attributen:

| Attribuut | Waarden |
|---|---|
| `view` | `list`, `grid` |
| `period` | `upcoming`, `past`, `all` |
| `limit` | positief getal met maximum |
| `columns` | `1` t/m `4` |
| `category` | één of meer slugs |
| `tag` | één of meer slugs |
| `filters` | `true`, `false` |
| `pagination` | `true`, `false` |
| `show_excerpt` | `true`, `false` |
| `show_image` | `true`, `false` |
| `show_location` | `true`, `false` |

### 9.2 Kalender

```text
[wpse_calendar
    initial_view="month"
    category=""
    tag=""
    filters="true"
    mobile_view="list"
]
```

### 9.3 Eventdetails

```text
[wpse_event_details]
```

Shortcodehandlers retourneren HTML en printen niet rechtstreeks. Attributen worden gewhitelist; vrije SQL-, metaquery- of callbackparameters zijn niet toegestaan. [WordPress Shortcode API](https://developer.wordpress.org/apis/shortcode/)

### 9.4 Meerdere shortcodes op één pagina

Elke shortcode/widget krijgt een stabiele instance-ID. Filter- en paginatieparameters worden per instance genamespaced, zodat twee eventlijsten op dezelfde pagina elkaar niet beïnvloeden.

## 10. Kalenderarchitectuur

### 10.1 Bibliotheek

Gebruik alleen de noodzakelijke niet-premium FullCalendar-onderdelen:

- core;
- day grid;
- list view;
- benodigde locale.

Geen drag-and-drop-, resource-, recurrence- of schedulerplugins.

De standaardonderdelen zijn MIT-gelicentieerd. [FullCalendar license](https://fullcalendar.io/license)

De bibliotheek wordt:

- lokaal meegeleverd;
- beperkt gebundeld;
- alleen geladen wanneer een kalender wordt gerenderd;
- niet via een externe CDN opgehaald.

### 10.2 Publieke REST-feed

```text
GET /wp-json/wpse/v1/events
```

Parameters:

- `start`: ISO 8601, verplicht;
- `end`: ISO 8601, verplicht en exclusief;
- `categories`: kommagescheiden slugs;
- `tags`: kommagescheiden slugs;
- `page` en `per_page`: begrensd.

Het endpoint:

- retourneert alleen gepubliceerde events;
- selecteert events die de zichtbare periode overlappen;
- valideert alle parameters;
- beperkt het maximale bereik, bijvoorbeeld 400 dagen;
- limiteert resultaten;
- bevat geen private meta;
- retourneert geen onbeveiligde HTML.

WordPress eist een `permission_callback` voor eigen REST-routes, ook wanneer die publiek zijn. [WordPress: Adding Custom Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)

Voorbeeldresponse:

```json
{
  "id": 123,
  "title": "Workshop keramiek",
  "start": "2026-09-12T14:00:00+02:00",
  "end": "2026-09-12T17:00:00+02:00",
  "allDay": false,
  "status": "scheduled",
  "url": "https://example.com/events/workshop-keramiek/",
  "extendedProps": {
    "venue": "Atelier Noord",
    "categories": ["workshops"]
  }
}
```

Overlapvoorwaarde:

```text
event.end_utc >= requested.start
AND event.start_utc < requested.end
```

### 10.3 Kalenderervaring

- knop “Vandaag”;
- vorige en volgende periode;
- maand- en lijstweergave;
- mobiele lijstweergave;
- laadstatus;
- lege toestand met resetknop;
- zichtbare actieve filters;
- filterstate optioneel in de URL;
- klik opent de eventpagina;
- geen popup vereist;
- no-JavaScriptfallback met eventlijst.

### 10.4 Toegankelijkheid

- elk kalenderitem is een echte link;
- navigatie werkt met toetsenbord;
- filters hebben zichtbare labels;
- wijzigingen worden gemeld via `aria-live`;
- focus blijft logisch na navigatie;
- status wordt niet alleen met kleur aangegeven;
- mobiele lijst blijft volledig bruikbaar.

FullCalendar gebruikt WAI-ARIA-technieken, maar de eigen filters en fallback moeten afzonderlijk worden getest. [FullCalendar accessibility](https://fullcalendar.io/docs/accessibility)

## 11. Elementor-integratie

Elementor is optioneel en wordt pas gebouwd nadat de native beheer-, template-, shortcode- en kalenderlaag stabiel zijn. Dat is de aanbevolen volgorde: de integratie wordt dan een dunne adapter boven bewezen kernlogica in plaats van een tweede implementatie.

De kernplugin blijft werken wanneer Elementor ontbreekt of gedeactiveerd wordt.

### 11.1 Voorbereidingen die al in de native fase nodig zijn

Hoewel de widgets later worden gebouwd, moeten deze contracten vanaf fase 1 stabiel zijn:

- `wpse_event` is publiek, querybaar, zichtbaar in REST en heeft `has_archive => true`;
- eventdata wordt via geregistreerde, getypeerde meta aangeboden;
- `EventRepository` bevat alle querylogica;
- `EventRenderer` en template-parts zijn herbruikbaar buiten globale loops;
- shortcodes accepteren gestructureerde, gewhiteliste argumenten die later naar widgetcontrols kunnen worden gemapt;
- assets worden geregistreerd met stabiele handles en alleen door renderers geënqueued;
- HTML-classes en CSS custom properties zijn stabiel en genamespaced;
- lege velden worden door de renderer conditioneel behandeld;
- een eventdetailsrenderer accepteert expliciet een event-ID, zodat Elementor previewdata kan leveren;
- native templatekeuze blokkeert Theme Builder niet;
- publieke hooks en filters zijn gedocumenteerd.

Met deze voorbereiding hoeft de Elementorfase geen opslag, queries of templates opnieuw uit te vinden.

### 11.2 Widgets

#### Event List/Grid

- lijst of raster;
- periode;
- aantal events;
- kolommen per breakpoint;
- categorieën en tags;
- afbeelding, samenvatting en locatie tonen;
- paginering;
- kleuren, typografie, spacing, borders en buttons.

#### Event Calendar

- initiële weergave;
- categorieën en tags;
- filters;
- mobiele lijstweergave;
- kleuren en typografie.

#### Event Details

- datum en tijd;
- locatie;
- adres;
- externe links;
- status;
- categorieën en tags;
- conditioneel verbergen van lege waarden.

Elementor-widgets gebruiken officiële widget- en controlinterfaces. [Elementor widgets](https://developers.elementor.com/docs/widgets/) en [Elementor widget controls](https://developers.elementor.com/docs/widgets/widget-controls/index.html)

Scripts worden via WordPress geregistreerd en via `get_script_depends()` aan widgets gekoppeld. [Elementor widget scripts](https://developers.elementor.com/docs/scripts-styles/widget-scripts/index.html)

### 11.3 Previewcontext

De detailswidget kan in de editor een previewevent kiezen. Zonder geldige eventcontext verschijnt een duidelijke editorplaceholder, geen willekeurig publiek event.

### 11.4 Dynamic Tags

Optioneel voor Elementor Pro:

- Event Start Date;
- Event Start Time;
- Event End;
- Event Date Range;
- Event Venue;
- Event Address;
- Event URL;
- Event Status.

### 11.5 Compatibiliteit

- controleer `elementor/loaded`;
- vereis voor de integratiemodule minimaal Elementor 3.35;
- test zowel een recente 3.x-compatibiliteitslijn als de actuele 4.x-lijn;
- verifieer het minimum opnieuw bij aanvang van fase 4;
- initialiseer alleen de integratiemodule als die compatibel is;
- toon hoogstens een contextuele adminmelding;
- laat shortcodes, templates en eventbeheer altijd functioneren.

Elementor adviseert addons een expliciete minimumversie te geven en oude compatibility branches niet onbeperkt mee te dragen. [Elementor compatibility checks](https://developers.elementor.com/docs/addons/compatibility/index.html) De bij de start van fase 4 actuele Elementorrelease is 4.1.5 en vereist WordPress 6.8+, maar de plugin blijft bewust bruikbaar zonder Elementor. [Elementor op WordPress.org](https://wordpress.org/plugins/elementor/)

## 12. WooCommercegrens

Events zijn redactionele content en geen producten of bestellingen. Daarom:

- geen `Requires Plugins: woocommerce`;
- geen order- of checkoutlogica;
- geen WooCommerce-sessies;
- geen scripts op winkelwagen of checkout;
- geen invloed op HPOS;
- geen eventmenu onder WooCommerce.

Een latere eenvoudige productlink kan `_wpse_product_id` toevoegen. Volledige ticketing blijft een afzonderlijke toekomstige module. Wanneer ooit ordergegevens worden gebruikt, moet uitsluitend de publieke WooCommerce CRUD-laag worden gebruikt en moet HPOS-compatibiliteit worden getest. [WooCommerce compatibility](https://developer.woocommerce.com/docs/extensions/best-practices-extensions/compatibility)

## 13. SEO en structured data

Een individuele eventpagina kan JSON-LD `Event` bevatten met:

- `name`;
- `startDate`;
- `endDate`;
- `eventStatus`;
- `url`;
- `image`;
- `description`;
- `location`.

Regels:

- structured data alleen op de individuele eventpagina;
- alleen zichtbare en correcte gegevens opnemen;
- hele-dag-events als datum weergeven;
- tijd met correcte UTC-offset;
- geen offers of prijsdata zonder echte verkoopinformatie;
- output uitschakelbaar maken om duplicatie met een SEO-plugin te vermijden.

Google beveelt JSON-LD aan als onderhoudbaar formaat, maar garandeert geen rich results. [Google: Event structured data](https://developers.google.com/search/docs/appearance/structured-data/event)

## 14. Prestaties

### 14.1 Geen eigen tabel

Voor deze beperkte scope is CPT plus postmeta de juiste afweging:

- native WordPress;
- eenvoudig beheer;
- REST- en Elementorcompatibel;
- geen schemamigraties;
- makkelijk exporteerbaar;
- voldoende voor een normale hoeveelheid eenmalige events.

Een eigen tabel wordt pas overwogen als echte profiling bij een grote eventcatalogus een probleem aantoont.

### 14.2 Queryregels

- kalender vraagt alleen de zichtbare periode op;
- lijsten hebben paginering of een harde limiet;
- datumqueries gebruiken de afgeleide UTC-meta;
- filters worden gesanitized;
- geen onbeperkte “alle events”-REST-call;
- actieve en voorbije queries worden centraal in `EventRepository` gebouwd;
- cache wordt ongeldig na event-, status- of taxonomiewijziging.

### 14.3 Assets

- adminassets alleen op eventbeheerpagina’s;
- lijst-CSS alleen waar eventoutput staat;
- kalender-JS alleen bij een kalender;
- geen globale jQuery-afhankelijkheid indien vanilla JavaScript volstaat;
- geen externe fonts, tracking of CDN-assets.

## 15. Beveiliging en privacy

Bij beheeracties:

- nonce controleren;
- `current_user_can()` controleren;
- autosaves en revisies correct behandelen;
- `wp_unslash()` vóór sanitization;
- tekst passend saneren;
- URL’s met `esc_url_raw()` opslaan;
- output pas op het laatste moment escapen;
- REST-argumenten voorzien van schema, validation en sanitization callbacks.

Specifieke risico’s:

- alleen `publish` via de publieke feed;
- titels en locaties als tekst behandelen;
- geen private of verborgen meta retourneren;
- geen willekeurige CSS-klassen uit gebruikersinput;
- externe links veilig renderen;
- maximale lengtes voor alle tekstvelden.

Versie 1 verwerkt geen deelnemers- of klantgegevens. Daardoor zijn normaal geen persoonlijke-data-exporters of erasers nodig.

## 16. Technische gaps en maatregelen

### 16.1 Archiefslug versus gewone Elementorpagina

Een WordPress-pagina met slug `events` botst met het CPT-archief `/events/`. De installatiecontrole moet een conflict detecteren en een duidelijke keuze aanbieden: CPT-archief gebruiken of een andere archiefslug instellen.

### 16.2 Validatie in verschillende editors

Opslaan via Classic Editor, Block Editor, REST en eventuele bulkacties moet dezelfde validator gebruiken. De validatieregels mogen niet alleen in JavaScript bestaan.

### 16.3 Cache-invalidatie

Cache vervalt bij:

- datum- of tijdwijziging;
- statuswijziging;
- publiceren, depubliceren of verwijderen;
- categorie- of tagwijziging;
- locatie- of titelwijziging wanneer REST-output wordt gecachet.

Gebruik een globale cacheversie of event-specifieke sleutelversie.

### 16.4 Meerdere weergaven op één pagina

Iedere shortcode of Elementor-widget heeft een unieke instance-ID. Filter-, view- en paginatieparameters worden genamespaced.

### 16.5 Eventstatus versus publicatiestatus

Een gepubliceerd maar geannuleerd event blijft publiek met een duidelijke melding. `post_status` en `_wpse_event_status` mogen nooit door elkaar worden gebruikt.

### 16.6 Uninstall en data-eigendom

De plugin verwijdert standaard geen events bij uninstall. Alleen een expliciete instelling mag pluginopties en eventdata definitief opruimen.

### 16.7 Herstel en diagnose

Benodigd:

- pluginversie en settingsversie;
- health check voor ontbrekende of ongeldige eventdata;
- veilige herberekening van UTC-meta;
- WP-CLI-commando voor validate/reindex als latere beheerhulp;
- logbare fouten zonder persoonsgegevens.

---

# Deel II — Analyse als power user

## 17. Dagelijks eventbeheer

Een power user verwacht vooral een snelle, voorspelbare workflow:

- nieuw event maken zoals een blogpost;
- datum en locatie onmiddellijk vinden;
- event dupliceren voor een nieuwe datum;
- duidelijke validatiefouten;
- aankomende events bovenaan;
- filters die hun laatste selectie niet onverwacht verliezen;
- geen overvolle instellingenpagina.

## 18. Event dupliceren als praktische vervanging

Omdat herhalende events buiten scope vallen, wordt dupliceren belangrijker.

Ideale flow:

1. Klik “Dupliceer”.
2. Nieuw concept opent.
3. Titel en inhoud zijn gekopieerd.
4. Datumvelden zijn gemarkeerd als nog te controleren.
5. Beheerder kiest nieuwe datum en publiceert.

Een optionele actie “Dupliceer zonder datum” kan nog veiliger zijn.

## 19. Kalenderervaring voor bezoekers

Minimale verwachtingen:

- Vandaag-knop;
- vorige/volgende maand;
- maand- en lijstweergave;
- mobiele lijst;
- categorie- en tagfilters;
- actieve filters zichtbaar;
- alles resetten;
- loadingstate;
- foutstate;
- lege toestand;
- eventtitel en eventueel tijd rechtstreeks leesbaar;
- klik naar volledige eventpagina.

Een popup is niet noodzakelijk en kan de mobiele en toegankelijke ervaring verslechteren.

## 20. Lijsten en rasters

Power users willen controle over:

- aankomend, voorbij of alles;
- aantal items;
- lijst of raster;
- kolommen per breakpoint;
- afbeelding aan/uit;
- samenvatting aan/uit;
- locatie aan/uit;
- filters;
- paginering.

De plugin moet zelf een nette standaardstijl leveren, maar thema en Elementor mogen die stijl eenvoudig kunnen overschrijven.

## 21. Locatie-ervaring zonder interactieve kaart

Voor bezoekers zijn de nuttigste locatie-elementen:

1. correcte locatienaam;
2. volledig kopieerbaar adres;
3. optionele link “Open route”;
4. aanvullende parkeer- of toegankelijkheidsinformatie in de eventtekst.

Er worden geen kaarttegels, markers, coördinaten of geocodingservices gebruikt. De beheerder plakt desgewenst zelf een externe locatie-URL.

Wanneer dezelfde locaties vaak terugkomen, kan later een eenvoudige lijst met opgeslagen venues worden toegevoegd. Dat is niet nodig voor versie 1.

## 22. Add to Calendar

Een `.ics`-download voor één event is een relatief kleine uitbreiding met hoge gebruikerswaarde.

De download bevat:

- stabiele `UID`;
- `DTSTART` en `DTEND`;
- tijdzone;
- titel;
- beschrijving;
- locatie;
- URL;
- correcte status.

**Prioriteit:** `should have` voor versie 1.1, niet vereist voor de eerste release.

## 23. Statuscommunicatie

Bezoekers moeten onmiddellijk zien:

- geannuleerd;
- uitgesteld;
- gewijzigde datum indien de beheerder dit in de inhoud vermeldt;
- fysieke locatie;
- tijdzone indien relevant.

Status wordt met tekst en visueel weergegeven, niet alleen met kleur.

## 24. Beheerfilters en bulkwerk

Minimaal nuttige filters:

- aankomend;
- actief;
- voorbij;
- geannuleerd;
- uitgesteld;
- categorie.

Nuttige bulkacties:

- publicatiestatus;
- eventstatus;
- categorieën en tags.

Quick edit van datum en tijd is risicovol en niet nodig in versie 1.

## 25. Lege, fout- en loadingstates

- Geen events gevonden: resetknop tonen.
- Kalenderfeed faalt: servergerenderde lijstfallback tonen.
- Geen afbeelding: nette kaartverhouding zonder kapot beeld.
- Geen eindtijd: geen leeg label tonen.
- Geen locatie: locatieblok volledig weglaten.
- Ongeldige externe link: niet renderen.

Progressive enhancement is belangrijker dan animaties of popups.

---

# Deel III — Definitieve productspecificatie

## 26. Scope per prioriteit

### Must have

- `wpse_event` Custom Post Type;
- titel, inhoud, samenvatting, afbeelding en revisies;
- start, einde, hele dag en tijdzone;
- eventstatus;
- locatie, adres en externe locatielink;
- eventcategorieën en eventtags;
- event dupliceren;
- individuele eventpagina;
- eventarchief;
- lijst, raster en kalender;
- categorie- en tagfilters;
- shortcodes;
- Elementor lijst-, kalender- en detailswidget;
- fallbacktemplates zonder Elementor;
- veilige publieke REST-feed;
- mobiele en no-JavaScriptfallback;
- behoud van voorbije events.

### Should have

- `.ics`-download voor één event;
- URL-gebonden filters;
- JSON-LD Event;
- admin health check;
- WP-CLI validate/reindex;
- testbare theme template-overrides.

### Could have

- herbruikbare venues zonder kaartfunctionaliteit;
- online/hybride eventtype;
- gekoppeld WooCommerce-product;
- CSV-import;
- frontend event-inzendingen.

### Expliciet niet bouwen

- terugkerende events;
- recurrence-regels of occurrences;
- interactieve kaarten;
- coördinaten en geocoding;
- multi-eventkaart;
- ticketing;
- deelnemersbeheer;
- QR-check-in;
- stoelplannen;
- automatische kalender- of kaartintegraties.

## 27. Aanbevolen codearchitectuur

```text
wp-simple-events/
├── wp-simple-events.php
├── uninstall.php
├── readme.txt
├── composer.json
├── package.json
├── src/
│   ├── Plugin.php
│   ├── Infrastructure/
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   └── Capabilities.php
│   ├── Content/
│   │   ├── EventPostType.php
│   │   ├── EventTaxonomies.php
│   │   └── EventMeta.php
│   ├── Domain/
│   │   ├── Event.php
│   │   ├── EventDateRange.php
│   │   ├── EventValidator.php
│   │   └── EventFormatter.php
│   ├── Repository/
│   │   └── EventRepository.php
│   ├── Admin/
│   │   ├── EventMetaBox.php
│   │   ├── EventSaveHandler.php
│   │   ├── EventDuplicator.php
│   │   ├── EventColumns.php
│   │   ├── EventFilters.php
│   │   └── SettingsPage.php
│   ├── Frontend/
│   │   ├── Assets.php
│   │   ├── ArchiveQuery.php
│   │   ├── TemplateLoader.php
│   │   ├── EventRenderer.php
│   │   └── Shortcodes.php
│   ├── Rest/
│   │   └── EventFeedController.php
│   ├── Calendar/
│   │   └── IcsExporter.php
│   ├── Seo/
│   │   └── EventSchema.php
│   ├── Cli/
│   │   └── EventsCommand.php
│   └── Integrations/
│       └── Elementor/
│           ├── ElementorIntegration.php
│           ├── EventListWidget.php
│           ├── EventCalendarWidget.php
│           └── EventDetailsWidget.php
├── templates/
│   ├── single-wpse_event.php
│   ├── archive-wpse_event.php
│   └── parts/
├── assets/
│   ├── src/
│   └── dist/
├── languages/
└── tests/
    ├── Unit/
    ├── Integration/
    └── E2E/
```

### Architectuurregels voor AI

- Geen bedrijfslogica in templates.
- Geen `WP_Query` rechtstreeks in Elementor-widgets.
- Alle eventqueries via `EventRepository`.
- Alle datumconversie via `EventDateRange` of een datumservice.
- Shortcodes en widgets gebruiken dezelfde renderer.
- REST gebruikt dezelfde repository en validator.
- Classes hebben één duidelijke verantwoordelijkheid.
- Functies, hooks, assets en opties gebruiken de `wpse_`-prefix.
- Geen eigen databasetabel.
- Geen code of datavelden voorbereiden voor functies die expliciet buiten scope zijn.

## 28. Minimale instellingenpagina

### Algemeen

- Archiefslug, standaard `events`.
- Events per archiefpagina.
- Standaardperiode: aankomend of alles.
- Voorbije events in standaardqueries tonen/verbergen.

### Weergave

- WordPress-datumformaat of aangepast formaat.
- WordPress-tijdformaat of aangepast formaat.
- Standaard lijst of raster.
- Event structured data aan/uit.

### Geavanceerd

- Plugindata verwijderen bij uninstall, standaard uit.
- Eventcapabilities herstellen.
- Eventcache leegmaken.
- UTC-indexmeta herberekenen.

Tijdzone en eerste weekdag worden uit WordPress overgenomen en niet als dubbele plugininstellingen aangeboden.

## 29. Teststrategie

### Unit tests

- lokale tijd naar UTC;
- zomer- en wintertijd;
- hele-dag-event;
- meerdaags event;
- ontbrekend eindmoment;
- eind vóór start;
- overlapberekening;
- statusvalidatie;
- URL-validatie;
- shortcode-attributen;
- formattering.

### WordPress-integratietests

- CPT- en taxonomieregistratie;
- capabilities;
- publicatievalidatie;
- geregistreerde meta in REST;
- aankomende, actieve en voorbije queries;
- concepten uitgesloten uit REST;
- dupliceren maakt een concept;
- templateprioriteit;
- rewrite rules alleen wanneer nodig;
- cache-invalidatie;
- uninstall behoudt standaard eventdata.

### End-to-endtests

- event maken in Block Editor;
- event bewerken;
- ongeldig event kan niet publiceren;
- event dupliceren en nieuwe datum kiezen;
- archief en single openen;
- categorie- en tagfilters;
- kalendermaand wijzigen;
- mobiel naar lijstweergave;
- toetsenbordnavigatie;
- Elementor-widget toevoegen en aanpassen;
- werking met WooCommerce actief;
- werking zonder Elementor;
- fout- en empty states.

### Kwaliteitscontroles

- WordPress Coding Standards;
- PHPStan;
- PHPUnit;
- JavaScript linting;
- automatische browsertests;
- PHP 8.1 t/m 8.4;
- actuele WordPress-versie;
- actuele WooCommerce-versie;
- actuele Elementor Free en Pro;
- klassiek thema en block theme;
- debugmodus zonder warnings of notices.

## 30. Bouwfasering

### Fase 1 — Content en beheer

- pluginbootstrap;
- CPT;
- taxonomieën;
- meta;
- datumservice;
- metabox;
- validatie;
- capabilities;
- beheerkolommen en filters;
- dupliceren;
- activatie en deactivatie.

### Fase 2 — Native front-end

- repository;
- archive query;
- fallbacktemplates;
- template-parts;
- lijst- en rastershortcode;
- responsive styling;
- paginering en filters.

### Fase 3 — Kalender

- REST-feed;
- overlapqueries;
- beperkte FullCalendar-build;
- maand- en lijstweergave;
- categorie- en tagfilters;
- no-JavaScriptfallback;
- toegankelijkheid.

### Fase 4 — Elementor

- conditionele integratie;
- lijst/raster-widget;
- kalenderwidget;
- detailswidget;
- style controls;
- editor-preview;
- optionele Pro dynamic tags.

### Fase 5 — Hardening

- JSON-LD;
- optionele `.ics`-download;
- caching;
- tests;
- documentatie;
- vertalingen;
- uninstallgedrag;
- compatibiliteitsmatrix;
- releasebuild.

## 31. Acceptatiecriteria

De plugin is pas klaar wanneer:

- activatie geen fouten veroorzaakt met WooCommerce en Elementor actief;
- de plugin bruikbaar blijft zonder Elementor;
- “Events” een eigen hoofdmenu heeft;
- een bevoegde editor events kan beheren;
- een event zonder startdatum niet kan publiceren;
- een eindmoment vóór het startmoment wordt geweigerd;
- een lopend meerdaags event bij actuele events staat;
- voorbije events via hun permalink bereikbaar blijven;
- het standaardarchief geen afgelopen events toont;
- dupliceren een nieuw concept maakt;
- categorie- en tagfilters in lijst en kalender werken;
- twee shortcodes op dezelfde pagina elkaar niet beïnvloeden;
- ongeldige shortcodeattributen veilig terugvallen;
- de kalender alleen de zichtbare periode opvraagt;
- concepten en private events nooit in de publieke feed staan;
- kalenderitems de correcte eventpagina openen;
- alle front-endweergaven mobiel bruikbaar zijn;
- de kalender met toetsenbord te bedienen is;
- zonder JavaScript een bruikbare eventlijst zichtbaar blijft;
- Elementor-widgets dezelfde query- en renderlogica gebruiken als shortcodes;
- CSS en JavaScript alleen worden geladen waar nodig;
- permalinks direct na activatie werken;
- de plugin events niet stilzwijgend verwijdert bij uninstall;
- alle zichtbare teksten vertaalbaar zijn;
- debugmodus geen PHP-warnings, REST-errors of console-errors toont;
- er geen recurrence-, geocoding- of interactieve-kaartcode in de release zit.

---

## 32. Definitief advies

De versoberde scope is technisch sterker voor het concrete doel. De plugin blijft dicht bij het native WordPress-contentmodel en vermijdt de twee onderdelen die het snelst tot een zwaar systeem zouden leiden.

De juiste versie 1 is daarom:

- één eventpost per feitelijk eventmoment;
- een snelle dupliceeractie voor vergelijkbare of opnieuw georganiseerde events;
- tekstuele locatiegegevens met optionele externe route-link;
- lijst, raster en maandkalender;
- filters en goede mobiele fallback;
- Elementor als dunne laag boven dezelfde renderers;
- geen eigen tabel;
- geen externe kaartdiensten;
- geen recurrence-engine.

Dat maakt de plugin klein zonder amateuristisch te worden. De overblijvende complexiteit zit waar ze waarde levert: correcte datumqueries, heldere beheerflows, toegankelijke kalenderoutput, veilige REST, goede templates en onderhoudbare integraties.

---

## 33. Belangrijkste primaire bronnen

- [WordPress — Registering Custom Post Types](https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/)
- [WordPress — `register_post_type`](https://developer.wordpress.org/reference/functions/register_post_type/)
- [WordPress — Registered meta in REST](https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/)
- [WordPress — Shortcode API](https://developer.wordpress.org/apis/shortcode/)
- [WordPress — Adding Custom REST Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [WordPress — Activation and Deactivation Hooks](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/)
- [WordPress — Releases](https://wordpress.org/download/releases/)
- [WordPress — Hosting Requirements](https://wordpress.org/about/requirements/)
- [PHP — Supported Versions](https://www.php.net/supported-versions.php)
- [FullCalendar — Event Sources](https://fullcalendar.io/docs/eventSources)
- [FullCalendar — Accessibility](https://fullcalendar.io/docs/accessibility)
- [FullCalendar — License](https://fullcalendar.io/license)
- [Elementor — Widgets](https://developers.elementor.com/docs/widgets/)
- [Elementor — Compatibility Checks](https://developers.elementor.com/docs/addons/compatibility/index.html)
- [Elementor — Current Plugin Requirements](https://wordpress.org/plugins/elementor/)
- [WooCommerce — Compatibility and Interoperability](https://developer.woocommerce.com/docs/extensions/best-practices-extensions/compatibility)
- [Google — Event Structured Data](https://developers.google.com/search/docs/appearance/structured-data/event)
