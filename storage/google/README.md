# Google Sheets setup

Target spreadsheet:
https://docs.google.com/spreadsheets/d/14Rum5hOhAcfQoDrXGBDYMKCUc-fWrjFAYM-Ivn4QMtw/edit

1. Create a Google Cloud service account and enable **Google Sheets API**.
2. Download the JSON key → save as `storage/google/service-account.json`.
3. Open the spreadsheet → Share → add the service account `client_email` as **Editor**.
4. `GOOGLE_SHEETS_ENABLED` is already `true` in config pointing at spreadsheet id `14Rum5hOhAcfQoDrXGBDYMKCUc-fWrjFAYM-Ivn4QMtw`.

On every form / refund submission, a new row is appended to Sheet1.
