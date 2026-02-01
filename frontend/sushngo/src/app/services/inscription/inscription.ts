import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

// joaquim

@Injectable({
  providedIn: 'root',
})
export class InscriptionService {
  private apiUrl = 'https://sae301-303.onrender.com/api.php';

  constructor(private http: HttpClient) { }

  // add un user
  inscription(userData: any): Observable<any> {
    const dataForApi = {
      nom: userData.nom,
      prenom: userData.prenom,
      email: userData.email,
      password: userData.password,
      tel: userData.tel,
      adresse: userData.adresse,
      statut_etud: userData.statut_etud ? 1 : 0
    };

    return this.http.post(this.apiUrl, dataForApi, {
      withCredentials: true
    });
  }
}