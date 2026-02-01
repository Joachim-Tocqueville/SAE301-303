/* réalisé par Joachim */
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

/* export interface utilisateur {
  id_user: number;
  api_token: string;
  nom: string;
  prenom: string;
  email: string;
  password: string;
  statut_etud: boolean; // 0 ou 1 (tinyint est l'équivalent de boolean)
  tel: number;
  adresse: string;
  fidelite: number;
}*/

@Injectable({
  providedIn: 'root',
})
export class ConnexionApi {
  protected API_URL = "https://sae301-303.onrender.com/api/api.php";

  // oral sae conseil : a redirigé vers le json / d'une autre manière

  constructor(private http: HttpClient) { }

  getUserDataFromApi() {
    return this.http.get(`${this.API_URL}`);
  }

  getStats() {
    return this.http.get(`${this.API_URL}?action=stats`); // action stats sert à récupérer les stats
  }

  inscription(inscriptionData: {
    nom: string,
    prenom: string,
    email_inscr: string,      // ← Nom Angular
    mdp_inscr: string,        // ← Nom Angular
    telephone: string,
    adresse: string,
    etudiant: string
  }): Observable<any> {

    // Transformation des données pour l'API
    const dataForApi = {
      nom: inscriptionData.nom,
      prenom: inscriptionData.prenom,
      email: inscriptionData.email_inscr,
      password: inscriptionData.mdp_inscr,
      telephone: inscriptionData.telephone,
      adresse: inscriptionData.adresse,
      statut_etud: inscriptionData.etudiant ? 1 : 0
    };

    return this.http.post(`${this.API_URL}`, dataForApi, {
      withCredentials: true
    });
  }


  logout() {
    localStorage.removeItem('token');
  }
}