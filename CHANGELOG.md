# Changelog — WootsApp Notifier

Toutes les modifications notables sont documentées ici.

Format : [Versioning sémantique](https://semver.org/lang/fr/)

---

## [1.6.1] — 2026-07-09

### Corrigé
- **Normalisation téléphone Côte d'Ivoire et Bénin** : `WTAN_Phone::normalize()` retirait à tort le premier chiffre des numéros CI et BJ, causant des échecs d'envoi WhatsApp
  - CI : depuis la réforme du 31/01/2021, les numéros comportent 10 chiffres pleins (préfixe opérateur `07`/`05`/`01`/`08`/`09` inclus) — ce chiffre n'est pas un zéro de tronc à retirer
  - BJ : depuis la réforme du 30/11/2024, tous les numéros ont un préfixe fixe `01` intégré, également conservé désormais
  - Ex: `0768319147` avec pays `CI` → `2250768319147@s.whatsapp.net` (corrige l'exemple erroné publié en 1.5.0)
  - Nouvel indicateur interne `trunk_zero` par pays dans la map pour distinguer les deux comportements
- **Validation renforcée** : un numéro déjà préfixé d'un indicatif international (≥10 chiffres, pas de "0" initial) est désormais rejeté si sa longueur ne correspond pas à celle attendue pour le pays de facturation connu, au lieu d'être envoyé tel quel (évite l'envoi vers des numéros incomplets, ex: ancien format CI/BJ saisi avec le nouvel indicatif)
- Le fallback "8 chiffres → Burkina Faso" ne s'applique plus que si le pays de facturation est totalement inconnu — évite qu'un numéro CI/BJ mal saisi (ancien format) soit silencieusement mal orienté vers un numéro burkinabè

---

## [1.6.0] — 2026-04-30

### Ajouté
- **Infos de licence dans le message WhatsApp** : la variable `{licences}` affiche désormais deux informations supplémentaires issues de LicenceFlow :
  - 🔁 Nombre d'utilisations autorisées (`delivre_x_times`) — affiché uniquement si > 1
  - ⏳ Date limite d'utilisation (`expiration_date`) — affichée si renseignée, formatée en français (`j F Y`)
  - La note manuelle `license_note` reste affichée en dernier comme auparavant
- **Normalisation téléphone** : ajout de deux pays à la map :
  - TD — Tchad (+235, 8 chiffres locaux)
  - CF — République Centrafricaine (+236, 8 chiffres locaux)

---

## [1.5.0] — 2026-04-05

### Ajouté
- **Export CSV des logs** : bouton "Exporter en CSV" dans la page Logs — génère un fichier `wootsapp-logs-YYYY-MM-DD.csv` avec BOM UTF-8 (compatible Excel / Google Sheets)
- **Normalisation téléphone par pays de facturation** : `WTAN_Phone::normalize()` accepte désormais le code pays ISO (ex: `CI`, `SN`, `FR`) et utilise la map d'indicatifs pour préfixer automatiquement les numéros locaux sans indicatif
  - Map couvrant 23 pays : BF, CI, SN, ML, GN, TG, BJ, NE, CM, FR, MA, GH, NG, CD, CG, MR, SL, LR, RW, KE, TZ, BE, CH
  - Ex: `0768319147` avec pays `CI` → `225768319147@s.whatsapp.net` (au lieu d'échouer)
  - Compatibilité ascendante : 8 chiffres sans pays toujours traités comme BF

---

## [1.4.0] — 2026-03-26

### Ajouté
- Variable `{note_client}` : affiche la note laissée par le client lors de la commande (`$order->get_customer_note()`) — vide automatiquement si aucune note
- **Mise à jour automatique depuis GitHub** : WordPress détecte désormais les nouvelles versions publiées sur `github.com/tedisun/wootsapp-notifier` et les propose via la page Plugins > Mises à jour
  - Nouvelle classe `WTAN_Updater` — hook `pre_set_site_transient_update_plugins`
  - Modale "Voir les détails" alimentée par les informations de la release GitHub
  - Cache 12h pour éviter les appels répétés à l'API GitHub
- Release ZIP restructuré : les fichiers sont maintenant dans un sous-dossier `wootsapp-notifier/` (requis par WordPress pour l'installation automatique)

---

## [1.3.0] — 2026-03-26

### Ajouté
- Variable `{telechargements}` : affiche les liens de téléchargement (Google Docs, Drive, PDF…) associés aux produits de la commande
- Format : `📄 Nom du guide\nURL` — une entrée par fichier téléchargeable configuré sur le produit WooCommerce
- Vide automatiquement si aucun produit de la commande n'a de téléchargement associé

---

## [1.2.0] — 2026-03-26

### Ajouté
- **Rebrand complet** : "Presellia WhatsApp Notify" → "WootsApp Notifier" par Tedisun SARL
  - Fichier principal : `wootsapp-notifier.php`
  - Préfixe classes/options/hooks : `WTAN_` / `wtan_`
  - Repo GitHub : `tedisun/wootsapp-notifier`
- **Migration automatique** : les options `pwan_*` sont copiées vers `wtan_*` au premier chargement (aucune reconfiguration nécessaire pour les installations existantes)
- **Intégration LicenceFlow** : nouvelle variable `{licences}` dans le template
  - Option "Inclure les licences" dans les réglages (affichée uniquement si LicenceFlow est actif)
  - Formatage automatique par type : `key`, `account`, `link`, `code`
  - Indicateur visuel dans les réglages (LicenceFlow détecté / non détecté)
  - Variable `{licences}` grisée dans l'éditeur de template si LicenceFlow absent

### Modifié
- Message de test : "WootsApp Notifier by Tedisun" (était "L'équipe Presellia")
- Template par défaut : texte générique sans référence à Presellia
- Nettoyage des lignes vides consécutives dans `render_template()` (variables vides n'ajoutent plus de sauts de ligne superflus)

---

## [1.1.0] — 2026-03-25

### Ajouté
- **Support multi-pays** : tout numéro ≥ 10 chiffres après nettoyage est accepté tel quel
- **Menu admin autonome** : plugin déplacé vers un menu de premier niveau "WA Notify"
- **Template de message configurable** : page dédiée avec éditeur et variables cliquables
- Variables : `{prenom}`, `{nom}`, `{prenom_nom}`, `{numero_commande}`, `{date_commande}`, `{total}`, `{monnaie}`, `{produits}`, `{nb_articles}`
- Filtre `wtan_message_variables` (était `pwan_message_variables`) pour variables tierces

---

## [1.0.0] — 2026-03-25

### Ajouté
- Envoi automatique WhatsApp à la confirmation commande (statut "Terminé")
- Normalisation numéros Burkina Faso (226, 00226, +226, local 8 chiffres)
- Protection anti-double envoi (`_wtan_notification_sent`)
- Client Evolution API (`wp_remote_post`)
- Page de réglages et de logs dans wp-admin
- Note privée sur commande (succès / échec)
- Compatibilité HPOS déclarée
- GitHub Actions : release automatique sur tag `vX.X.X`
