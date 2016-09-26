# GisChat Telegram Bot

A Telegram Bot based on the official [Telegram Bot API](https://core.telegram.org/bots/api)

## Table of Contents
- [Introduction](#introduction)
- [Instructions](#instructions)
- [Documentation](#documentation)
- [License](#license)
- [Credits](#credits)

## Introduction
This is the source code of the GisChatBot ( @GisChatBot) Telegram Bot based on the official [Telegram Bot API]( https://core.telegram.org/bots/api ). 
The bot implements a software agent that help a telegram user in the documentation of an exeperience of active tourism.  
The application is designed for people that don't have familiarity with GIS and use light maps to permits the management and visualization of the user roadbooks in a easy way.
For example the edit of paths that connect waypoints of a trip is automatized and use web services like Mapzen (https://mapzen.com/)        
The @GisChatBot is available from a Telegram Client in desktop or mobile devices.   
The maps managed in the application are showed using the browser of the device 
At the moment the web services integrated in the maps are Google and OpenStreetMap both for base map tiles and Mapzen for directions services but in the future can be easily added some other useful services (e.g elevation service).
The bot deployed on the server gisepi.crs4.it at CRS4 integrate data and furnish directions services for pathways of the area "Gutturru Mannu" in Sardinia (Italy) ()

## Instructions
The source code is located in two folder: the "src" folder with the php implementation of the bot; the "map" folder with the impelmentation of maps (javascript + html + css) and services (php).
The bot data is stored in a database on a PostgreSQL OR-DBMS with postgis functionalities. To see the structure of the database open the DBtemplate.sql  
The bot receives updates via a webhook that is implemented in the script webhook.php. The script settings.php contains the keys and constants for the database and telegram connections. 
The main classes are: Telegram (Telegram.php) that manages updates; DB (DB.php) that manages the database read\write operation; Request (Request.php) that manage the 

## Documentation
The bot manages a set of Comands and Inline Queries that can be see with the bot comand /help. 

### Commands 
/language - Set your language
/begin - Start tracking your trip
/setprivate - Set your current trip private
/setpublic - Set your current trip public
/setname - Set your current trip name
/setname name - Set your current trip name
/settag - Set a tag for your last registered position
/settag tagname - Set a tag(generic, danger or poi) for your last registered position
/show - Show your current trip
/end - Close your current trip
/confirm - Confirm your current trip, it must be closed
/help - Show full command description
/about - Show bot information

### Inline Query
@GisChatBot trips - Show all nearest public or owned trips
@GisChatBot trips keyword - Show all trips found using the given word
@GisChatBot trips number - Show all public or owned trips in a radius of specified kilometers
@GisChatBot search - Show all nearest areas
@GisChatBot search area name - Show a list of area found with the given word
@GisChatBot search number - Show a list of area in a radius of specified kilometers 

### Maps


[https://github.com/bobdemo/phpgischatbot/blob/master/1a.png|alt=1a](https://github.com/bobdemo/phpgischatbot/blob/master/1a.png)



## License
Please see the [LICENSE](LICENSE.md) included in this repository for a full copy of the MIT license,
which this project is licensed under.

## Credits

###  Elisa Pau,
 - email: eladomus07@gmail.com,
 - homepage: https://github.com/elaaa92,
 - role: Maintainer and Developer

###  Roberto Demontis,
 - email: demontis@crs4.it,
 - homepage: https://github.com/bobdemo,
 - role: Maintainer and Developer

