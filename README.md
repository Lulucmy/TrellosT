# TrellosT
osTicket + Trello integration plugin for easier ticket management. Tested with osTicket v1.18.1.
Inspired by [Slack](https://github.com/clonemeagain/osticket-slack) plugin, and other [Trello](https://github.com/jatacid/trello-simple) [plugins](https://github.com/kyleladd/OSTicket-Trello-Plugin).

## Features
- [x] Create a card on Trello when an ticket is opened on osTicket;
- - Contains ticket title, body, creation time and creator name.
- [x] Add ticket replies to its Trello card (as comments);
- - Contains reply body, sender and time.
- [x] Stores the Trello card ID in osTicket (so you can move the card freely);
- [x] Multiple instances possible (tickets can be sent to different Trello boards/lists). 

## Install
1. Head to https://trello.com/power-ups/admin and create a new power-up;
2. In your power-up > API Keys > Copy your API key and save it somewhere. Then click on "manually generate a token", authorize it and save your token somewhere;
3. On your Trello board, open your brower dev. tools and search for your list ID. It should look like `data-list-id="x97zaer456trez45r1z5r6e1"`. Save it somewhere too; 
4. Clone this repo and move the `TrellosT-Plugin` folder inside your osTicket plugin folder : `/osticket/include/plugins/`;
5. You need to create a custom field in your osTicket forms (`/scp/forms.php`) and give it a variable name (`cardId` for example). Variable name must be consistent across all forms and field visibility must be set to 'Internal, Optional';
6. Go to your osTicket instance `/scp/plugins.php` and click on "Add a new plugin" > "Install";
7. Create a new instance of the plugin and fill the form with the previous info;
8. Enable the plugin instance and the plugin globally.

## Plugin options
- **Trello API Key** : Your Trello Power-up API key, available at https://trello.com/power-ups/admin > *Your power-up name* > API Key;
- **Trello API Token** : Your Trello Power-up token, available at https://trello.com/power-ups/admin > *Your power-up name* > API Key > Manual token;
- **Trello List ID** : Your Trello list ID, available on your Trello board. Inspect element and find it, it should be under `data-list-id`;
- **Trello Card ID Custom Field** : Your osTicket custom field variable name. Each Trello card created will be stored in a custom field on your ticket;
- **Trello Label Status Color** : The color of the card label that will contain the ticket creator name (red/green/blue/black/white are working; others to test).

## Todo
- [ ] Add ticket status as label on Trello
- [ ] Archive card on Trello when ticket is archived
