## Start le serveur en local

```
symfony server:start --allow-all-ip --p12=cert.p12
```

## Mercure en local (HTTPS + certif auto-signé)

Le hub Mercure (Caddy) génère sa propre CA locale, stockée dans le volume Docker
`mercure_data`. PHP ne fait pas confiance à cette CA par défaut, ce qui casse le
publish (`unable to get local issuer certificate`) tant qu'on ne lui donne pas
explicitement le certificat root à faire confiance.

### Régénérer les certificats (si le volume `mercure_data` a été supprimé, ex: `docker compose down -v`)

1. Redémarrer le hub :
   ```bash
   docker compose up
   ```
   Caddy régénère une nouvelle CA racine dans `mercure_data` au premier démarrage.

2. Extraire le nouveau certificat root du conteneur :
   ```bash
   docker compose cp mercure_hub:/data/caddy/pki/authorities/local/root.crt ./mercure-root-ca.crt
   ```

3. Reconstruire le bundle CA combiné (CA système + CA Caddy) utilisé par PHP :
   ```bash
   cat /opt/homebrew/etc/openssl@3/cert.pem ./mercure-root-ca.crt > /opt/homebrew/etc/php/8.5/combined-ca-bundle.pem
   ```
   `php.ini` (`curl.cainfo` et `openssl.cafile`) pointe déjà vers ce fichier — pas besoin
   de le retoucher, juste de le régénérer avec la nouvelle CA.

4. Redémarrer `symfony server:start` pour que PHP-FPM relise le nouveau bundle.

5. (Optionnel, pour tester sur téléphone Android) réinstaller la CA sur le téléphone :
   ```bash
   adb push ./mercure-root-ca.crt /sdcard/Download/mercure-root-ca.crt
   ```
   Puis dans le téléphone : Paramètres > Sécurité > Chiffrement et identifiants >
   Installer un certificat > Certificat CA.

### Vérifier que ce n'est pas plutôt un souci de JWT

Le hub valide les JWT signés avec `MERCURE_JWT_SECRET`. Cette valeur doit être
**identique** des deux côtés :
- `.env` (racine, committé) → lu par `docker compose` pour `MERCURE_PUBLISHER_JWT_KEY`/`MERCURE_SUBSCRIBER_JWT_KEY`
- `.env.local` (gitignored) → lu par Symfony pour signer les JWT publiés

Si les deux valeurs diffèrent, le publish échoue avec une erreur **401**, pas
une erreur TLS (`unable to get local issuer certificate`). Se fier au message
d'erreur exact (loggé via `MercurePublisherService`) pour savoir lequel des
deux problèmes on a réellement en face.
