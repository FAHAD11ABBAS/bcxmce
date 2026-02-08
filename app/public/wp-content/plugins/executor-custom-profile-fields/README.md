Tämä lisäosa laajentaa WordPressin käyttäjäprofiilin hallintaa lisäämällä mukautettuja kenttiä ja toimintoja, kuten nimityst (Kokelas, Jäsen, Kunniajäsen), VIP-merkinnät, laskutus- ja koulutustiedot sekä kunniajäsenyyteen liittyvät lisätiedot. Se mahdollistaa myös käyttäjäprofiilin päivitysten seurantaan, mukaan lukien viimeisin päivitysaika, tehdyt muutokset ja päivittäjä (käyttäjä tai ylläpitäjä). Lisäksi lisäosa poistaa sähköpostivahvistukset ja ilmoitukset sähköpostimuutoksista ylläpitäjän hallinnan tehostamiseksi. Lisäosa sisältää dynaamisia kenttiä, mukautetun ulkoasun ja hallintapaneelin lajittuvat sarakkeet käyttäjäprofiilin tiedoille.

Profile-fields.php
Tämä koodi mukauttaa WordPressin käyttäjäprofiilin kenttiä ja toiminnallisuuksia.

Koodissa lisätään uusia profiilikenttiä, kuten:

    •	Käyttäjän nimitys (Kokelas, Jäsen, Kunniajäsen)
    •	VIP-merkinnät (kruunu tai risti profiilikuvan viereen)
    •	Kunniajäsenyyteen liittyvät tiedot, kuten kunniajäsenen numero ja nimitämisvuosi
    •	Yhteystiedot (puhelinnumero, yritys, moottoripyörä, alue)
    •	Laskutustiedot (sähköposti, osoite, postinumero, kaupunki, maa)
    •	Koulutustiedot (ensiapu- ja tilannejohtamiskoulutuksen päivämäärät)

Kentät tallennetaan käyttäjän metatietoihin ja niitä käsitellään lomakkeen avulla. Lomake käyttää WordPressin get_user_meta ja update_user_meta -toimintoja tietojen hakemiseen ja tallentamiseen.

Toiminnot ja ominaisuudet:

    •	Käyttäjäprofiilissa näytettävät kentät riippuvat käyttäjän valitsemasta nimitystä (esim. kunniajäsenet näkevät lisäkenttiä).
    •	Käyttäjä voi ladata profiilikuvan, joka tallennetaan WordPressin mediakirjastoon.
    •	Lomakkeella voi hallita näkyvyysasetuksia, kuten piilottaa sähköpostin tai puhelinnumeron muilta.
    •	Käyttäjille voidaan määrittää Fennoa-laskutusjärjestelmää varten asiakasnumero ja laskutustiedot.
    •	Käyttäjäprofiilin ulkoasua yksinkertaistetaan piilottamalla WordPressin oletuskenttiä, kuten rikkaan tekstin muokkaus ja avatar-asetukset
    Deaktivoidun jäsenen tietoja voi halutessaan tuoda näkyviin (jos jäsen on esim Kunniajäsen)

Huomioitavaa:

    •	Lisätyt kentät päivitetään tallennuksen yhteydessä ja validoidaan mahdollisuuksien mukaan.

• Mukautettu ulkoasu ja piilotetut kentät toteutetaan CSS:n avulla.

Disable.php
Tämä koodi mukauttaa WordPressin käyttäjän sähköpostiosoitteen muutoksiin liittyviä asetuksia ja toimintaa.

Toiminnot ja ominaisuudet:

    1.	Sähköpostimuutoksiin liittyvien ilmoitusten poistaminen:
    •	add_filter('send_email_change_email', '__return_false'); poistaa sähköpostimuutoksen ilmoituksen, joka normaalisti lähetettäisiin WordPress-järjestelmänvalvojalle.
    •	add_filter('send_user_request', '__return_false'); estää vahvistussähköpostin lähettämisen käyttäjälle sähköpostiosoitteen muutoksen yhteydessä.
    2.	Sähköpostin vahvistuksen ohittaminen ylläpitäjälle:
    •	Funktio bypass_email_confirmation_for_admin mahdollistaa WordPress-järjestelmänvalvojien (joilla on oikeus “manage_options”) muuttaa käyttäjän sähköpostiosoitetta suoraan ilman vahvistussähköpostia.
    •	Muutos tallennetaan suoraan tietokantaan käyttäen WordPressin $wpdb-objektia, ja käyttäjän välimuisti päivitetään clean_user_cache-funktiolla.

Käyttötarkoitus:

    •	Tämä koodi sopii tilanteisiin, joissa järjestelmänvalvoja haluaa hallita käyttäjien sähköpostiosoitteita manuaalisesti ilman ylimääräisiä ilmoituksia tai vahvistuksia.
    •	Koodilla vähennetään sähköpostiliikennettä, mikä voi olla hyödyllistä erityisesti suurille sivustoille tai yhteisöille.

Huomioitavaa:

    •	Koodi varmistaa, että sähköpostimuutokset voidaan tehdä vain ylläpitäjien toimesta.
    •	Uuden sähköpostiosoitteen syöte puhdistetaan sanitize_email-funktiolla ennen sen tallentamista tietokantaan.
    •	Käyttäjän sähköpostivahvistuksen ohittaminen voi heikentää tietoturvaa, joten tätä toimintoa tulisi käyttää harkiten.

User-profile-tracker.php
Tämä koodi lisää käyttäjäprofiilien päivitysten seurantatoiminnon WordPress-sivustolle.

Toiminnot ja ominaisuudet:

    1.	Profiilipäivitysten tallentaminen:
    •	Funktio upt_save_last_updated tallentaa käyttäjän profiilin viimeisimmän päivityksen ajankohdan _profile_last_updated-metatietoon.
    •	Muutosten seurantaa varten koodi vertaa uutta ja vanhaa dataa. Muutetut kentät tallennetaan _profile_changes-metatietoon, joka sisältää sekä vanhat että uudet arvot.
    •	Muutosten tekijä tallennetaan _profile_changed_by-metatietoon (User tai Admin sen mukaan, kuka muutoksen teki).
    2.	Tietojen näyttäminen käyttäjäprofiilissa:
    •	Funktio upt_show_last_updated_field näyttää seuraavat tiedot käyttäjäprofiilisivulla:
    •	Viimeisin päivitysajankohta
    •	Muutettujen kenttien lista
    •	Päivityksen tekijä
    •	Näitä tietoja ei voi muokata, vaan ne ovat vain lukuoikeudella.
    3.	Viimeisimmän päivityksen tiedot hallintapaneelissa:
    •	Funktiot upt_admin_user_column ja upt_display_last_updated_column lisäävät hallintapaneelin käyttäjälistaukseen kaksi uutta saraketta:
    •	“Last Updated” (viimeisin päivitysaika)
    •	“Changed By” (päivittäjä)
    •	Sarakkeet näyttävät kyseiset tiedot jokaiselle käyttäjälle.
    4.	Sarakkeiden lajittelu:
    •	Funktio upt_sortable_columns tekee “Last Updated”- ja “Changed By”-sarakkeista lajittelukelpoisia, mikä mahdollistaa käyttäjälistan järjestämisen näiden tietojen perusteella.

Käyttötarkoitus:

    •	Koodi sopii tilanteisiin, joissa on tarpeen seurata käyttäjäprofiilien muutoksia, esimerkiksi yhteisösivustoilla tai yrityksissä, joissa ylläpitäjien on tiedettävä, milloin ja kuka on tehnyt muutoksia.
    •	Tämä parantaa läpinäkyvyyttä ja helpottaa hallintaa usean käyttäjän järjestelmissä.

Huomioitavaa:

    •	Muutosten seuranta koskee vain niitä kenttiä, jotka kuuluvat käyttäjäprofiiliin tai lähetetään päivityksessä.
    •	Tietoja voidaan käyttää myös auditointitarkoituksiin.
    •	Koodi ei kata kaikkia mahdollisia lisäosia, jotka voivat muokata käyttäjäprofiileja omilla menetelmillään.
