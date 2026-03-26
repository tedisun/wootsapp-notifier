# Changelog — WootsApp Notifier

Toutes les modifications notables sont documentées ici.

Format : [Versioning sémantique](https://semver.org/lang/fr/)

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
