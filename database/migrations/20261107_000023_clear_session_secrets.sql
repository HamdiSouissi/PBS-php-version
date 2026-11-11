-- Les identifiants de session ne doivent pas être conservés dans les journaux.
UPDATE auth_login_logs SET session_id = '' WHERE session_id <> '';
