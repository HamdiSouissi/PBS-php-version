# Signature électronique simple des contrats clients

## Périmètre et limites

La fonctionnalité associe deux consentements explicites à une copie figée du
PDF et à son empreinte SHA-256. Il s'agit d'une **signature électronique
simple**, non qualifiée : aucun certificat de signature, horodatage qualifié,
contrôle d'identité indépendant ou scellement externe n'est fourni. Le client
est identifié uniquement par la possession d'un lien secret ; une personne à
qui le lien est transmis peut signer à sa place. Le fichier contrat lui-même
n'est pas signé cryptographiquement : le justificatif PDF accompagne la copie
figée. Une personne ayant accès en écriture à la base et au stockage peut
altérer les preuves. Faire valider l'adéquation et la valeur probante du
procédé par un conseil juridique ; utiliser un prestataire qualifié lorsque le
niveau de garantie nécessaire l'exige.

## Mise en service

- Sauvegarder puis appliquer les migrations : `php bin/console migrate:run`.
- Configurer `APP_URL` avec l'URL publique HTTPS, `APP_ENV=production`,
  `APP_DEBUG=false` et `SESSION_SECURE=true`. Vérifier le certificat TLS.
- Pour l'envoi par mail, configurer les paramètres SMTP `MAIL_*` et tester la
  délivrabilité. Si l'envoi échoue, l'application le signale et le lien reste
  affiché pour un envoi manuel. L'envoi SMTP n'est pas une preuve de réception.
- Le processus PHP doit pouvoir écrire dans `storage/contract-signatures`,
  dossier privé hors racine web. Sauvegarder **ensemble** ce dossier et la
  base de données (y compris les enregistrements `contract_signatures`) avec
  une stratégie de conservation, de protection et de restauration testée.
  Ne pas supposer que `backup:run` sauvegarde ce dossier : vérifier votre
  solution de sauvegarde et l'ajouter explicitement si nécessaire.
- Les liens personnels figurent dans les URL : configurer les journaux d'accès
  du serveur et du proxy pour ne pas enregistrer les chemins `/signature/`,
  limiter l'accès aux journaux existants et définir leur rétention. Le code
  masque le chemin dans son journal d'exceptions, mais ne contrôle pas les
  journaux de l'hébergeur ou des intermédiaires. Éviter tout outil tiers de
  suivi sur les pages de signature.

## Utilisation

1. Générer et relire le dernier PDF d'un contrat client. Sur sa fiche, saisir
   le nom et l'e-mail du signataire, sélectionner le lien manuel ou l'e-mail,
   puis confirmer la signature pour le prestataire. La permission
   `contracts.write` est requise. Le consentement porte sur le PDF affiché ;
   si un autre PDF a été généré entre-temps, relire avant de recommencer.
2. Copier le lien personnel affiché une seule fois et le transmettre en privé,
   ou choisir l'envoi par e-mail. Le lien expire après 7 jours. Si le lien est
   perdu ou compromis, révoquer l'invitation dans la fiche et en créer une
   nouvelle. Une seule invitation non expirée et non révoquée peut être en
   attente pour un même contrat.
3. Le client ouvre le lien, télécharge le contrat et coche son consentement.
   Après signature, il peut télécharger le contrat figé et le justificatif
   pendant la durée de validité du lien. Une seconde signature est refusée.
4. La fiche du contrat conserve l'historique et permet aux utilisateurs
   autorisés de télécharger le PDF figé et, une fois les deux signatures
   effectuées, le justificatif. Les PDF régénérés ensuite ne modifient pas la
   copie signée. L'empreinte de cette copie est vérifiée avant toute signature
   ou téléchargement.

Les heures du justificatif sont celles du serveur ; maintenir son horloge à
l'heure. Les adresses IP peuvent provenir d'en-têtes proxy non fiables et ne
sont pas une preuve d'identité. Les IP et navigateurs sont des données
personnelles : appliquer une politique de confidentialité, de conservation et
d'accès appropriée. La configuration, l'accès au lien et les sauvegardes doivent
être contrôlés avant toute utilisation en production.
