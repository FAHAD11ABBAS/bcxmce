Tämä WordPress-laajennus tarjoaa kolme lyhytkoodia, jotka parantavat käyttäjäprofiilien hallintaa ja esittämistä. [display_user_info] näyttää käyttäjän profiilitiedot ja mahdollistaa niiden muokkaamisen, mukaan lukien salasanan vaihto ja profiilikuvan päivitys. [display_selected_user_profile] näyttää yksittäisen käyttäjän profiilin mukautetulla näkymällä, ja [display_all_users] listaa kaikki käyttäjäprofiilit hakutoiminnolla, suodattimilla ja tyylikkäillä taulukko- tai ruudukkonäkymillä. Laajennus tukee piilotettavia tietoja, VIP-merkintöjä ja responsiivista muotoilua, parantaen käyttökokemusta ja yksityisyyttä.

Display-user-info.php
Tämä WordPress-koodi luo lyhytkoodin [display_user_info], joka näyttää käyttäjän profiilitiedot ja mahdollistaa niiden päivittämisen suoraan etusivulta. Käyttäjä voi muokata tietoja, kuten nimeä, sähköpostiosoitetta, puhelinnumeroa, yritystä ja muita profiilikohtaisia tietoja. Lyhytkoodi tarjoaa myös mahdollisuuden vaihtaa profiilikuvan ja päivittää salasana. Salasanan vaihtotoiminto sisältyy lyhytkoodiin käyttäen omaa lomaketta ja nonce-tarkistusta turvallisuuden varmistamiseksi.

Tyylittelyt ovat huolellisesti suunniteltu CSS-koodilla, joka käyttää modernia ja responsiivista muotoilua. Profiilisivun ominaisuuksiin kuuluu:

    •	Käyttäjän tiedot, kuten nimi, nimitys, alue, jäsenyystiedot ja koulutushistoria.
    •	Profiilin näkyvyysasetukset, jotka sallivat sähköpostin ja puhelinnumeron piilottamisen muilta käyttäjiltä.
    •	Mahdollisuus päivittää laskutustiedot.
    •	“Kunniajäsen”-ominaisuus näkyy käyttäjille, joille se on määritetty.
    •	Visuaaliset elementit, kuten VIP-kruunu ja valinnaiset ikonit profiilikuvaan.

Tämä lyhytkoodi parantaa käyttäjäkokemusta tarjoamalla selkeän ja helposti käytettävän tavan hallita käyttäjäprofiilia ilman erillistä kirjautumista WordPressin hallintapaneeliin.

Lisäksi mukana on toinen lyhytkoodi [custom_password_change_form], joka lisää erillisen salasananvaihtolomakkeen. Tämä lomake tarkistaa nykyisen salasanan oikeellisuuden, varmistaa uuden salasanan vahvistuksen ja päivittää salasanan turvallisesti käyttäjän profiiliin.

Display-one-profile.php
Tämä koodi luo WordPress-shortkoodin [display_selected_user_profile], joka näyttää yksittäisen käyttäjän profiilitiedot mukautetulla näkymällä. Profiilin tiedot haetaan käyttäjänimen perusteella, joka välitetään URL-osoitteessa parametrina. Näkymä sisältää mm. profiilikuvan, käyttäjän perustiedot, yrityksen, nimitysn, kunniajäsenyystiedot, viimeisen kirjautumisajankohdan ja muut käyttäjän metatiedot. Käyttäjä voi tarkastella omia tietojaan ja tarvittaessa siirtyä profiilin muokkaussivulle.

Lisäksi koodi sisältää:

    •	Ensiapukoulutus- ja tilannejohtamiskurssitiedot: Näytetään, jos tiedot on tallennettu.
    •	Piilotettavat tiedot: Sähköpostin ja puhelinnumeron näkyvyyttä voidaan hallita käyttäjäkohtaisesti.
    •	Profiilin ulkoasu ja tyylit: Mukautetut CSS-tyylit, jotka tekevät profiilinäkymästä responsiivisen ja käyttäjäystävällisen.
    •	Viimeisen kirjautumisajankohdan tallennus: Käyttäjän kirjautuessa hänen viimeinen kirjautumisajankohtansa tallennetaan automaattisesti metatietona.
    •	VIP- ja kunniajäsenmerkinnät: Profiilissa näkyy erikoismerkkejä (esim. kruunu-ikoni), jos käyttäjä on VIP-jäsen tai kunniajäsen.

Shortkoodi voidaan lisätä mille tahansa WordPress-sivulle, ja se mukautuu automaattisesti käyttäjän näkyvyysasetusten mukaisesti. Tämä ratkaisu sopii erityisesti organisaatioille, jotka haluavat esitellä käyttäjäprofiileja tarkasti ja tyylikkäästi.

Display all users.php

Tämä koodi on WordPressin PHP-pohjainen shortcode-funktio, jonka tarkoituksena on näyttää käyttäjäprofiileita verkkosivustolla. Se sisältää hakutoiminnon, suodattimet ja erilaisia käyttäjätietoja, jotka haetaan WordPressin käyttäjämeta-arvoista. Koodi mahdollistaa käyttäjien profiilien esittämisen ruudukkonäkymässä tai taulukkonäkymässä sekä sisältää seuraavat ominaisuudet:

    •	Hakutoiminto: Käyttäjät voivat hakea profiileita etsimällä etu-, suku- tai näyttönimien perusteella.
    •	Alue- ja nimitys-suodattimet: Käyttäjäprofiileja voidaan rajata alueen (esim. Pirkanmaa) tai nimitysn (esim. Jäsen, Kunniajäsen) mukaan.
    •	Käyttäjätiedot: Profiilit sisältävät seuraavia tietoja:
    •	Nimi, nimitys ja jäsennumero
    •	Kunniajäsenstatus ja kunniajäsennumero
    •	Profiilikuva ja VIP-ikonit
    •	Yhteystiedot (sähköposti, puhelinnumero), jos ne ovat julkisia
    •	Moottoripyörä, yritys ja alue
    •	Suoritetut koulutukset (esim. ensiapu ja tilannejohtamiskurssi)
    •	Viimeisin sisäänkirjautumispäivä
    •	Profiilin muokkaus ja katselu: Nykyiselle käyttäjälle näytetään “Muokkaa profiiliasi”-painike. Muiden käyttäjien profiileihin voi siirtyä “Näytä profiili”-linkin kautta.
    •	VIP- ja symboli-ikonit: Käyttäjäprofiilit voivat sisältää VIP-symbolin tai ristin.

Tekniset ominaisuudet:

    •	Käyttäjäprofiilien haku ja lajittelu: Profiilit lajitellaan näyttönimen mukaan nousevaan järjestykseen.
    •	Käyttäjäkohtaiset metatiedot: Koodi käyttää WordPressin get_user_meta()-funktiota hakemaan lisätietoja, kuten osaston (department), nimitysn (nimitys), piilotettujen tietojen asetukset ja VIP-statuksen.
    •	Tiedot suodatetaan ja puhdistetaan: Hakutulokset ja metatiedot käsitellään turvallisuuden varmistamiseksi (sanitize_text_field, esc_attr, esc_html, jne.).
     • Taulukkonäkymä:
    •	HTML-taulukko on lisätty piilotettuna. Se sisältää sarakeotsikot käyttäjätiedoille, kuten nimi, numero, puhelin, sähköposti jne.
    •	Sarakkeet ovat lajittelukelpoisia, ja käyttäjä voi lajitella tiedot klikkaamalla sarakeotsikoita. Tämä toteutetaan sortTable-funktiolla JavaScriptissä.

• Näkymien vaihto:
• JavaScript-koodi mahdollistaa vaihtamisen ruudukko- ja taulukkonäkymien välillä (leveillä näytöillä) sekä listanäkymään mobiilissa.
• Näkymän vaihtopainike muuttaa dynaamisesti tekstiä ja toimintoa valitun näkymän mukaan.
• Haku ja suodatus:
• Reaaliaikainen hakukenttä suodattaa sekä ruudukon että taulukon sisältöä käyttäjän nimien ja tunnusten perusteella.
• Putoavalikko-suodattimet (osasto ja nimitys) piilottavat automaattisesti profiilit ja rivit, jotka eivät vastaa valittuja arvoja.
• CSS-tyylit:
• Taulukon sarakeotsikot on muotoiltu korostumaan hover-efekteillä ja lajittelusuunnan osoittavilla ikoneilla.
• Responsiiviset tyylit takaavat, että sisältö mukautuu hyvin eri kokoisille näytöille.
• Parannettu käyttäjäkokemus:
• Kirjautuneen käyttäjän profiili korostetaan tyylillisesti.
• Käytettävyyttä ja luettavuutta parannettu hover-efekteillä, tasaisella marginaalilla ja responsiivisella typografialla.
