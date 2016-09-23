<?php

namespace GisAgentTB\TelegramBot;

class Interpreter 
{   
    public static function getResponseData($state, $language)
    {
        $text = ""; 
        $buttonTexts = null;
        $inline = false;
        $reply = false;
        $log = null;
        $trackedAction = null;
        $defaultName = null;
        
        $textEn = '';
        $textIt = '';
        $buttonTextsEn = null;
        $buttonTextsIt = null;
        $defaultNameEn = null;
        $defaultNameIt = null;

        //choice è la scelta dell'utente, request è una domanda del bot
        switch ($state)
        {
            case SET_LANGUAGE_REQUEST:
            {
                $textEn = "Select Language.";
                $textIt = "Seleziona linguaggio.";
                $buttonTextsEn = array("English", "Italiano");
                $buttonTextsIt = $buttonTextsEn;
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Language selection");
                break;
            }
            case SET_LANGUAGE_CHOICE_EN:
            {
                $textEn = "English language set.";
                $textIt = "English language set.";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Language selected en");
                break;
            }
            case SET_LANGUAGE_CHOICE_IT: 
            {
                $textEn = "Lingua selezionata Italiano.";
                $textIt = "Lingua selezionata Italiano.";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Language selected it");
                break;
            }
            case SET_LANGUAGE_CHOICE_INTERNAL_ERROR:
            {
                $textEn = "Sorry, there was an error updating the language, try again.";
                $textIt = "Mi spiace, c'è stato un errore aggiornando la lingua, riprova.";
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Error selecting language");
                break;
            }
            case CREATE_TRACK_CHOICE_INTERNAL_ERROR:
            {
                $textEn = "Sorry, there was an error creating the track, try again.";
                $textIt = "Mi spiace, c'è stato un errore creando il percorso, riprova.";
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Error creating track");
                break;
            }
            case CREATE_CONCURRENT_TRACK_CHOICE:
            {
                $textEn = "You've got an open track already, use /end to close it.";
                $textIt = "Hai già iniziato un percorso, utilizza /end per chiuderlo.";
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "Trying to create a concurrent track");
                break;
            }
            case CREATE_TRACK_CHOICE_SET_TRACK_PRIVACY_REQUEST:
            {
                $textEn = "Do you want to make a public track? " .
                "If you tap no it will be visible only for the members of this chat.";
                $textIt = "Vuoi che il percorso sia visibile a tutti? Se premi no " .
                "sarà visibile solo ai membri di questa chat.";
                $buttonTextsEn = array("Yes", "No");
                $buttonTextsIt = array("Sì", "No");
                $trackedAction = "/start";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Track created");
                break;
            }
            case SET_PRIVATE_TRACK_CHOICE:
            {
                $textEn = "Only chat members will be able to see this track.";
                $textIt = "Il tuo percorso sarà visibile solo ai membri della chat.";
                $trackedAction = "privacy";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Track set private");
                break;
            }
            case SET_PUBLIC_TRACK_CHOICE:
            {
                $textEn = "Everybody will be able to see this track.";
                $textIt = "Il tuo percorso sarà visibile a tutti.";
                $trackedAction = "privacy";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Track set public");
                break;
            }
            case SET_PRIVATE_TRACK_CHOICE_SET_TRACK_NAME_REQUEST:
            {
                $textEn = "Only chat members will be able to see this track, how do you want to call it?";
                $textIt = "Il tuo percorso sarà visibile solo ai membri della chat, come vuoi chiamarlo?";
                $reply = true;
                $trackedAction = "privacy";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "New track set private");
                break;
            }
            case SET_PUBLIC_TRACK_CHOICE_SET_TRACK_NAME_REQUEST:
            {
                $textEn = "Everybody will be able to see this track, how do you want to call it?";
                $textIt = "Il tuo percorso sarà visibile a tutti, come vuoi chiamarlo?";
                $reply = true;
                $trackedAction = "privacy";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "New track set public");
                break;
            }
            case PRIVACY_ON_NOT_OPEN_TRACK_CHOICE:
            {
                $textEn = "You have no open track to set privacy.";
                $textIt = "Non hai tragitti aperti di cui aggiornare la privacy.";
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "No open track to set privacy");
                break;
            }
            case SET_TRACK_PRIVACY_CHOICE_INTERNAL_ERROR:
            {
                $textEn = "There was an error setting your track privacy, try again.";
                $textIt = "C'è stato un errore nell'impostazione della privacy, riprova.";
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Error setting privacy");
                break;
            }
            case SET_TRACK_NAME_REQUEST:
            {
                $textEn = "How do you want to call this track?";
                $textIt = "Come vuoi chiamare questo percorso?";
                $reply = true;
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Track name request");
                break;
            }
            case SET_IMPLICIT_TRACK_NAME_CHOICE:
            {
                $textEn = "Ok, I created the track. Previous inserted points has been added to your track. "
                        . "You can see your progress at any moment using /show."; 
                $textIt = "Ok, ho creato il percorso. Tutte le posizioni registrate in precedenza "
                        . "sono state aggiunte al percorso. Puoi vedere i progressi in qualsiasi momento usando /show.";
                $trackedAction = "name";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Implicit track created");
                break;
            }
            case SET_FIRST_TRACK_NAME_CHOICE:
            {
                $textEn = "Ok, I created the track. You can now add positions or file or see your progress by using /show.";
                $textIt = "Ok, ho creato il percorso. Ora puoi aggiungere posizioni o file o vedere i tuoi "
                        . "progressi usando /show.";
                $trackedAction = "name";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "First track name set");
                break;
            }
            case SET_TRACK_NAME_CHOICE:
            {
                $textEn = "Ok, I have set your track name.";
                $textIt = "Ok, ho inserito il nome del percorso.";
                $trackedAction = "name";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Track name set");
                break;
            }
            case NAME_ON_NOT_OPEN_TRACK_CHOICE:
            {
                $textEn = "You don't have any open track to give a name."; 
                $textIt = "Non hai alcun percorso a cui dare un nome."; 
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "No open track to give a name");
                break;
            }
            case SET_TRACK_NAME_CHOICE_INTERNAL_ERROR:
            {
                $textEn = "There was an internal error, try again."; 
                $textIt = "C'è stato un errore interno, riprova."; 
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Error giving track name");
                break;
            }
            case CREATE_FIRST_TRACKPOINT_CHOICE_SET_TAG_REQUEST:
            {
                $textEn = "Choose a tag for your registered point: 'Danger' if there is some problem in this place, 'Point of interest' if you think it's a nice place, otherwise choose 'Generic'.";      
                $textIt = "Scegli un tag per il punto che hai registrato: 'Pericolo' se c'è un problema in questo luogo, 'Punto di interesse' se pensi che sia un posto interessante, altrimenti scegli 'Generico'.";      
                $buttonTextsEn = array("Generic", "Danger", "Point of interest");
                $buttonTextsIt = array("Generico", "Pericolo", "Punto di interesse");
                $trackedAction = "/start";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Trackpoint created");
                break;
            }
            case CREATE_TRACKPOINT_CHOICE_SET_TAG_REQUEST:
            {
                $textEn = "Choose a tag for your registered point: 'Danger' if there is some problem in this place, 'Point of interest' if you think it's a nice place, otherwise choose 'Generic'.";      
                $textIt = "Scegli un tag per il punto che hai registrato: 'Pericolo' se c'è un problema in questo luogo, 'Punto di interesse' se pensi che sia un posto interessante, altrimenti scegli 'Generico'.";      
                $buttonTextsEn = array("Generic", "Danger", "Point of interest");
                $buttonTextsIt = array("Generico", "Pericolo", "Punto di interesse");
                $trackedAction = "point";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Trackpoint created");
                break;
            }
            case CREATE_LATE_TRACKPOINT_CHOICE_SET_TAG_REQUEST:
            {
                $textEn = "Remember to add new position often for a better accuracy. Choose a tag for your registered point: 'Danger' if there is some problem in this place, 'Point of interest' if you think it's a nice place, otherwise choose 'Generic'.";      
                $textIt = "Ricorda di aggiungere spesso una posizione per una migliore accuratezza. Scegli un tag per il punto che hai registrato: 'Pericolo' se c'è un problema in questo luogo, 'Punto di interesse' se pensi che sia un posto interessante, altrimenti scegli 'Generico'.";      
                $buttonTextsEn = array("Danger", "Point of interest", "Generic");
                $buttonTextsIt = array("Pericolo", "Punto di interesse", "Generico");
                $trackedAction = "point";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Late trackpoint created");
                break;
            }
            case SET_TAG_REQUEST:
            {
                $textEn = "Choose a tag for your registered point: 'Danger' if there is some problem in this place, 'Point of interest' if you think it's a nice place, otherwise choose 'Generic'.";      
                $textIt = "Scegli un tag per il punto che hai registrato: 'Pericolo' se c'è un problema in questo luogo, 'Punto di interesse' se pensi che sia un posto interessante, altrimenti scegli 'Generico'.";      
                $buttonTextsEn = array("Danger", "Point of interest", "Generic");
                $buttonTextsIt = array("Pericolo", "Punto di interesse", "Generico");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Tag request");
                break;
            }
            case CREATE_TRACKPOINT_CHOICE_INTERNAL_ERROR: 
            {
                $textEn = "Sorry, there was an error registering your position, try again.";      
                $textIt = "Mi spiace, c'è stato un errore nella registrazione della posizione, riprova."; 
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Error creating trackpoint");
                break;
            }
            case ILLEGAL_FILE_CHOICE:
            {
                $textEn = "I'm sorry but this kind of file is not supported.";
                $textIt = "Mi spiace, ma questo tipo di file non è supportato.";
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "Trying to insert an illegal file");
                break;
            }
            case UNBOUND_FILE_CHOICE:
            {
                $textEn = "You can't add a file without open a track, use /begin to get a new one.";
                $textIt = "Non puoi aggiungere alcun file senza aprire un percorso, usa /begin per" .
		" crearne uno nuovo.";
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "Tryng to insert a file without a open track");
                break;
            }
            case FILE_CHOICE:
            {
                $textEn = "Content added!";
                $textIt = "Contenuto aggiunto!";
                $trackedAction = "content";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "File added");
                break;
            }
            case LATE_FILE_CHOICE:
            {
                $textEn = "Content added! Remember to add often position for a better accuracy of your roadbook.";
                $textIt = "Contenuto aggiunto! Ricorda di aggiungere spesso una posizione per una migliore accuratezza del tuo diario.";
                $trackedAction = "content";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Late file added");
                break;
            }
            case CREATE_ROADBOOK_ELEMENT_CHOICE_INTERNAL_ERROR:
            {
                $textEn = "Sorry, there was an error registering your content, try again.";      
                $textIt = "Mi spiace, c'è stato un errore nella registrazione del content, riprova.";  
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Error creating roadbook element");
                break;
            }
            case SET_TAG_GENERIC_CHOICE_SET_TRACK_PRIVACY_REQUEST:
            {
                $textEn = "Ok, I added your tag. You hadn't an open track so I"
                            . " created a new one, do you want to make it public? If you say no it will be "
                            . "visible to chat members only.";
                $textIt = "Ok, ho aggiunto il tag. Non avevi percorsi aperti, perciò "
                            . "ne ho creato uno, vuoi renderlo pubblico? Se scegli no sarà visibile solo ai "
                            . "membri di questa chat.";
                $buttonTextsEn = array("Yes", "No");
                $buttonTextsIt = array("Sì", "No");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Tag generic added to first trackpoint of implicit track");
                break;
            }
            case SET_TAG_DANGER_CHOICE_SET_TRACK_PRIVACY_REQUEST:
            {
                $textEn = "Ok, I added your tag. You hadn't an open track so I"
                            . " created a new one, do you want to make it public? If you say no it will be "
                            . "visible to chat members only.";
                $textIt = "Ok, ho aggiunto il tag. Non avevi percorsi aperti, perciò "
                            . "ne ho creato uno, vuoi renderlo pubblico? Se scegli no sarà visibile solo ai "
                            . "membri di questa chat.";
                $buttonTextsEn = array("Yes", "No");
                $buttonTextsIt = array("Sì", "No");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Tag danger added to first trackpoint of implicit track");
                break;
            }
            case SET_TAG_POI_CHOICE_SET_TRACK_PRIVACY_REQUEST:
            {
                $textEn = "Ok, I added your tag. You hadn't an open track so I"
                            . " created a new one, do you want to make it public? If you say no it will be "
                            . "visible to chat members only.";
                $textIt = "Ok, ho aggiunto il tag. Non avevi percorsi aperti, perciò "
                            . "ne ho creato uno, vuoi renderlo pubblico? Se scegli no sarà visibile solo ai "
                            . "membri di questa chat.";
                $buttonTextsEn = array("Yes", "No");
                $buttonTextsIt = array("Sì", "No");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Tag poi added to first trackpoint of implicit track");
                break;
            }
            case SET_TAG_DANGER_CHOICE:
            {
                $textEn = "Ok, I added your tag.";                            
                $textIt = "Ok, ho aggiunto il tag.";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Tag danger added");
                break;
            }
            case SET_TAG_POI_CHOICE:
            {
                $textEn = "Ok, I added your tag.";                            
                $textIt = "Ok, ho aggiunto il tag.";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Tag poi added");
                break;
            }
            case SET_TAG_GENERIC_CHOICE:
            {
                $textEn = "Ok, I added your tag.";                            
                $textIt = "Ok, ho aggiunto il tag.";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Tag generic added");
                break;
            }
            case TAG_ON_NOT_OPEN_TRACK_CHOICE:
            {
                $textEn = "You have not an open track to set point tag."; 
                $textIt = "Non hai percorsi aperti in cui inserire i tag dei punti."; 
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "Tag on not open track");
                break;
            }
            case TAG_ON_NOT_EXISTENT_TRACKPOINT:
            {
                $textEn = "You have not a registered position to associate this tag."; 
                $textIt = "Non hai alcuna posizione registrata a cui associare il tag."; 
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "Tag on not existent trackpoint");
                break;
            }
            case SET_TAG_CHOICE_INTERNAL_ERROR:
            {
                $textEn = "There was an error setting your tag, try again."; 
                $textIt = "C'è stato un errore nell'inserimento del tag, riprova."; 
                $log = "Error setting tag.";
                break;
            }
            case EDIT_KEY_NOT_SET_INTERNAL_ERROR:
            {
                $textEn = "There was an error getting your track, try again."; 
                $textIt = "C'è stato un errore nel recupero del tuo percorso, riprova."; 
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Error setting edit key");
                break;
            }
            case SHOW_MAP_CHOICE:
            {
                $textEn = "Go to map of ";
                $textIt = "Vai alla mappa di ";
                $defaultNameEn = "Unnamed trip";
                $defaultNameIt = "Percorso senza nome";
                $buttonTextsEn = array("Map");
                $buttonTextsIt = array("Mappa");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Show map");
                break;
            }
            case SHOW_NOT_OPEN_TRACK_MAP_CHOICE:
            {
                $textEn = "You don't have any open track to show, use /begin to start a new one.";
                $textIt = "Non hai tragitti aperti da mostrare, usa /begin per iniziarne uno.";
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "Tryng to show track but track list is empty");
                break;
            }
            case END_TRACK_CHOICE_OVERHAUL_REQUEST:
            {
                $textEn = "Do you want to confirm this track? If you confirm your track you "
                        . "will no longer be able to add new content but only to manage the registered one. "
                        . "Be sure that all the content has been sent before you tap Confirm.";
                $textIt = "Vuoi confermare questo percorso? "
                        . "Se lo confermi non potrai più aggiungere nuovi contenuti ma potrai in "
                        . "ogni caso gestire quelli esistenti. Assicurati che tutti i contenuti siano "
                        . "stati inviati prima di premere Conferma.";
                $buttonTextsEn = array("Confirm"); 
                $buttonTextsIt = array("Conferma"); 
                $trackedAction = "stop";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Track closed");
                break;
            }
            case END_EMPTY_TRACK_CHOICE:
            {
                $textEn = "This track hadn't any position record in it, I deleted it.";
                $textIt = "Questo percorso non aveva alcuna posizione registrata, l'ho cancellato.";
                $trackedAction = "stop";
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "Tryng to close an empty track");
                break;
            }
            case END_NOT_EXISTENT_TRACK_CHOICE:
            {
                $textEn = "You don't have any open track to close.";
                $textIt = "Non hai alcun percorso aperto da chiudere.";
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "Tryng to close a not existent track");
                break;
            }
            case END_TRACK_CHOICE_INTERNAL_ERROR:
            {
                $textEn = "Sorry, there was an error closing your track, try again.";
                $textIt = "Mi dispiace ci sono stati errori nella chiusura del tracciato, riprova.";
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Error closing track");
                break;
            }
            case NO_OVERHAUL_TRACK_CHOICE:
            {
                $textEn = "Ok, I confirmed your track.";
                $textIt = "Ok, ho confermato il tuo percorso.";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Track validated");
                break;
            } 
            case CONFIRM_NOT_EXISTENT_TRACK_CHOICE:
            {
                $textEn = "You have no track to confirm.";
                $textIt = "Non hai alcun percorso da confermare.";
                $log = array("type" => "Warning", "message" => "State " . $state . ", ". "No track to confirm (user has not tracks)");
                break;
            }
            case CONFIRM_TRACK_INTERNAL_ERROR: 
            {
                $textEn = "There was an error confirming your track, try again."; 
                $textIt = "C'è stato un errore nella conferma del percorso, riprova."; 
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Track confirmation error");
                break;
            }
            case HELP_CHOICE:
            {
                $textEn = "/language - Set your language"
                ."\n/begin - Start tracking your trip"
                ."\n/setprivate - Set your current trip private"
                ."\n/setpublic - Set your current trip public"
                ."\n/setname - Set your current trip name"
                ."\n/setname <em>name</em> - Set your current trip name"
                ."\n/settag - Set a tag for your last registered position"
                ."\n/settag <em>tagname</em> - Set a tag(generic, danger or poi) for your last registered position"
                ."\n/show - Show your current trip"
                ."\n/end - Close your current trip"
                ."\n/confirm - Confirm your current trip, it must be closed"
                ."\n/help - Show full command description"
                ."\n/about - Show bot information"
                ."\n@GisChatBot trips - Show all nearest public or owned trips"
                ."\n@GisChatBot trips <em>keyword</em> - Show all trips found using the given word"
                ."\n@GisChatBot trips <em>number</em> - Show all public or owned trips in a radius "
                ."of specified kilometers"
                ."\n@GisChatBot search - Show an area list with some information about them"
                ."\n@GisChatBot search <em>area name</em> - Show a list of area found with the given word"
                ."\n@GisChatBot search <em>number</em> - Show a list of area in a radius of specified kilometers";
                $textIt = "/language - Scegli la lingua"
                ."\n/begin - Inizia a tracciare il tuo percorso"
                ."\n/setprivate - Rendi il tuo percorso corrente privato"
                ."\n/setpublic - Rendi il tuo percorso corrente pubblico"
                ."\n/setname - Scegli il nome per il tuo percorso corrente"
                ."\n/setname <em>nome</em> - Scegli il nome per il tuo percorso corrente"
                ."\n/settag - Scegli un tag per la più recente posizione registrata"
                ."\n/settag <em>tag</em> - Scegli un tag(generic, danger, poi) per la più recente posizione registrata"
                ."\n/show - Mostra il tuo percorso corrente"
                ."\n/end - Chiudi il tuo percorso corrente"
                ."\n/confirm - Conferma il tuo percorso corrente, deve prima essere chiuso"
                ."\n/help - Mostra la descrizione dei comandi"
                ."\n/about - Mostra le informazioni sul bot"
                ."\n@GisChatBot trips - Mostra tutti i percorsi pubblici e quelli "
                ."creati dall'utente localizzati nei paraggi"
                ."\n@GisChatBot trips <em>parola chiave</em> - Mostra tutti i percorsi "
                ."pubblici trovati utilizzando la parola data"
                ."\n@GisChatBot trips <em>numero</em> - Mostra tutti i percorsi pubblici nel raggio "
                ."del numero di chilometri specificato"
                ."\n@GisChatBot search - Mostra una lista delle aree nei paraggi con le relative informazioni"
                ."\n@GisChatBot search <em>nome area</em> - Mostra una lista delle aree con nome simile a "
                ."quello dato e alcune informazioni al riguardo"
                ."\n@GisChatBot search <em>numero</em> - Mostra una lista delle aree nel raggio del numero di " 
                ."chilometri specificato";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Help choice");
                break;
            }
            case ABOUT_CHOICE:
            {
                $textEn = "I am GisChatBot, I can help you finding a path to your meta or you can help me" .
		" suggesting some new path. You can also have a personal roadbook where you can save your trip, " . 
		"its places, appearence and sound, your feeling or some information useful for other people.";
                $textIt = "Il mio nome è GisChatBot, il mio compito è aiutarti a trovare una strada per la tua" .
		" meta, ma potresti anche essere tu stesso a suggerirmi un nuovo percorso! Puoi tenere traccia dei tuoi " . 
		"viaggi tramite un diario personale dove puoi raccogliere il percorso effettuato," .
		" ciò che hai visto, sentito o provato, ma anche dare delle informazioni utili" .
		" alle altre persone.";
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "About choice");
                break;
            }
            case OWNER_NOT_SET_INTERNAL_ERROR: 
            {
                $textEn = "There was an internal error, try again."; 
                $textIt = "C'è stato un errore interno, riprova."; 
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Owner not set");
                break;
            }
            case SEARCH_REQUEST: 
            {
                $textEn = "Show map of ";
                $textIt = "Mostra la mappa di ";
                $buttonTextsEn = array("Map");
                $buttonTextsIt = array("Mappa");
                $inline = array("inline_result_type" => "article");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Search result");
                break;
            }
            case SEARCH_REQUEST_INTERNAL_ERROR:
            {
                $textEn = "No results found";
                $textIt = "Nessun risultato";
                $inline = array("inline_result_type" => "article");
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Search internal error");
                break;
            }
            case TRIPS_REQUEST:
            {
                $textEn = "Show map of ";
                $textIt = "Mostra la mappa di ";
                $buttonTextsEn = array("Go to map");
                $buttonTextsIt = array("Vai alla mappa");
                $defaultNameEn = "Unnamed trip";
                $defaultNameIt = "Percorso senza nome";
                $inline = array("inline_result_type" => "article");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Trips result");
                break;
            }
            case INLINE_QUERY_REQUEST_NO_RESULTS_FOUND: 
            {
                $textEn = "No results found";
                $textIt = "Nessun risultato";
                $inline = array("inline_result_type" => "article");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "No results found");
                break;
            }
            case INLINE_RESULT_CHOICE:
            {
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Choosen inline message");
                break;
            }
            case INLINE_QUERY_REQUEST_INTERNAL_ERROR:
            {
                $textEn = "No results found";
                $textIt = "Nessun risultato";
                $inline = array("inline_result_type" => "article");
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Internal error");
                break;
            }
            case INLINE_OWNER_NOT_SET_INTERNAL_ERROR:
            {
                $textEn = "No results found";
                $textIt = "Nessun risultato";
                $inline = array("inline_result_type" => "article");
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Owner not set");
                break;
            }
            case SEARCH_RESULT_CHOICE:
            {
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Search feedback result");
                break;
            }
            case TRIPS_RESULT_CHOICE:
            {
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Trips feedback result");
                break;
            }
            case TRIPS_RESULT_CHOICE_MANAGE_TRACK_REQUEST:
            {
                $textEn = "If you want you can manage this track.";
                $textIt = "Se vuoi puoi modificare questo tragitto.";
                $buttonTextsEn = array("Go to edit map");
                $buttonTextsIt = array("Vai alla mappa di modifica");
                $log = array("type" => "Info", "message" => "State " . $state . ", ". "Manage track request");
                break;
            }
            case INVALID_CALLBACK_DATA:
            {
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Invalid callback data");
                break;
            }
            default:    //INTERNAL_ERROR e DB_INTERNAL_ERROR
            {
                $textEn = "There was an internal error, try again."; 
                $textIt = "C'è stato un errore interno, riprova."; 
                $log = array("type" => "Error", "message" => "State " . $state . ", ". "Generic error");
                break;
            }  
        }
        if($language == "en")
        {
            $text = $textEn;
            if(isset($buttonTextsEn))
            {
                $buttonTexts = $buttonTextsEn;
            }
            if(isset($defaultNameEn))
            {
                $defaultName = $defaultNameEn;
            }
        }
        else 
        {
            $text = $textIt;
            if(isset($buttonTextsIt))
            {
                $buttonTexts = $buttonTextsIt;
            }
            if(isset($defaultNameIt))
            {
                $defaultName = $defaultNameIt;
            }
        }
        return array("pieces" => array("text" => $text, "button_texts" => $buttonTexts, "default_name" => $defaultName,
            "inline" => $inline, "reply" => $reply), "log" => $log, "action" => $trackedAction);
    }
    
    public static function getSummaryData($summary, $language)
    {
        $textEn = "Here a summary of your recent actions. ";
        $textIt = "Ecco un riassunto delle tue azioni recenti. ";
       
        if($summary["trackNumber"] == 1)
        {
            $textEn .= "You managed " . $summary["trackNumber"] . " track";
            $textIt .= "Hai gestito " . $summary["trackNumber"] . " percorso";
        }
        else
        {
            $textEn .= "You managed " . $summary["trackNumber"] . " tracks";
            $textIt .= "Hai gestito " . $summary["trackNumber"] . " percorsi";
        }

        if($summary["closedTracks"] != 0)
        {
            $textEn .= " of which " . $summary["closedTracks"] . " closed";
            if($summary["closedTracks"] == 1)
            {
                $textIt .= " dei quali " . $summary["closedTracks"] . " chiuso";
            }
            else
            {
                $textIt .= " dei quali " . $summary["closedTracks"] . " chiusi";
            }
            if($summary["emptyClosedTracks"] == 1)
            {
                $textEn .= " and " . $summary["emptyClosedTracks"] . " was deleted because empty "
                    . "(with no points in them)";
                $textIt .= " e " . $summary["emptyClosedTracks"] . " è stato cancellato perché vuoto "
                    . "(privo di punti all'interno)";
            }
            else if($summary["emptyClosedTracks"] > 1)
            {
                $textEn .= " and " . $summary["emptyClosedTracks"] . " were deleted because empty "
                    . "(with no points in them)";
                $textIt .= " e " . $summary["emptyClosedTracks"] . " sono stati cancellati perché vuoti "
                    . "(privi di punti all'interno)";;
            }
        }
        $textEn .= ". ";
        $textIt .= ". ";

        if(isset($summary["current"]))
        {
            if(isset($summary["current"]["name"]) && !$summary["current"]["name"])
            {
                $textEn .= "You got a new track with no name";
                $textIt .= "Hai creato un nuovo percorso senza nome";
                if(isset($summary["current"]["privacy"]) && !$summary["current"]["privacy"])
                {
                    $textEn .= " and no privacy preferences set yet";
                    $textIt .= " e senza preferenze circa la privacy";
                }
                $textEn .= ". ";
                $textIt .= ". ";
            }
            else if(isset($summary["current"]["privacy"]) && !$summary["current"]["privacy"])
            {
                $textEn .= "You got a new track with no privacy preferences set yet. ";
                $textIt .= "Hai creato un nuovo percorso di cui non hai ancora impostato la privacy. ";
            }
            if($summary["current"]["points"] != 0)
            {
                if($summary["current"]["points"] == 1)
                {
                    $textEn .= "You added " . $summary["current"]["points"] . " new point";
                    $textIt .= "Hai aggiunto " . $summary["current"]["points"] . " nuovo punto";
                }
                else
                {
                    $textEn .= "You added " . $summary["current"]["points"] . " new points";
                    $textIt .= "Hai aggiunto " . $summary["current"]["points"] . " nuovi punti";
                }
                if($summary["current"]["contents"] == 1)
                {
                    $textEn .= " and " . $summary["current"]["contents"] . " new content";
                    $textIt .= " e " . $summary["current"]["contents"] . " nuovo contenuto";
                }
                else
                {
                    $textEn .= " and " . $summary["current"]["contents"] . " new contents";
                    $textIt .= " e " . $summary["current"]["contents"] . " nuovi contenuti";
                }
                $textEn .= " to your current track. ";
                $textIt .= " al tuo percorso corrente. ";
            }
            else if($summary["current"]["contents"] != 0)
            {
                if($summary["current"]["contents"] == 1)
                {
                    $textEn .= "You added " . $summary["current"]["contents"] . " new content to your current track. ";
                    $textIt .= "Hai aggiunto " . $summary["current"]["contents"] . " nuovo contenuto al tuo percorso corrente. ";
                }
                else
                {
                    $textEn .= "You added " . $summary["current"]["contents"] . " new contents to your current track. ";
                    $textIt .= "Hai aggiunto " . $summary["current"]["contents"] . " nuovi contenuti al tuo percorso corrente. ";
                }
            }  
        }
        if($language == "en")
        {
            return $textEn;
        }
        else 
        {
            return $textIt;
        }
    }
}
