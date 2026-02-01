import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject } from 'rxjs';
import { map, catchError, tap } from 'rxjs/operators'; // map sert a transformer les données  tap sert a faire des actions sur les données
import { of } from 'rxjs'; // of sert a creer un observable et rxjs est une librairie d'observables
import { PanierService } from './panierService/panierService';

export interface User {
  id_user: number;
  nom: string;
  prenom: string;
  email: string;
  statut_etud: boolean;
  tel?: string;
  adresse?: string;
  fidelite?: number;
}

export interface AuthResponse {
  success: boolean;
  reponse?: string;
  message?: string;
  error?: string;
  api_token?: string;
  user?: User;
}

export interface SessionStatus {
  logged_in: boolean;
  user_id: number | null;
  api_token: string | null;
  user?: User; // Ajout pour récupérer l'utilisateur à la vérification de session
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = 'https://sae301-303.onrender.com/api/api.php';
  private isLoggedInSubject = new BehaviorSubject<boolean>(false);
  public isLoggedIn$ = this.isLoggedInSubject.asObservable();
  private currentUserSubject = new BehaviorSubject<User | null>(null); // valeur = soit User complet soit null , behaviorSubject sert a sauvegarder les données de l'user , pour etre accessibles depuis les autres composants sans avoir besoin de les recharger
  public currentUser$ = this.currentUserSubject.asObservable();  //$ à la fin de currentUser signifie que c'est un observable et transforme le behaviorSubject en observable

  constructor(private http: HttpClient, private panierService: PanierService) {
    this.checkSession().subscribe();
  }


  // Connexion
  login(credentials: { email: string; password: string }): Observable<AuthResponse> { // credentials sont les données envoyées par le client
    return this.http.post<AuthResponse>(this.apiUrl, credentials, {
      withCredentials: true
    }).pipe( // pipe permet de faire des opérations sur les données qui arrivent
      tap(response => { // effectue des actions sur les données qui passent
        if (response.success) {
          this.isLoggedInSubject.next(true);
          // SI la connexion est réussie et qu'on a un utilisateur, on le sauvegarde
          if (response.user) {
            this.currentUserSubject.next(response.user);
          }
        }
      })
    );
  }

  // il faudra utiliser dans la connexion de angular inscripconex :

  // this.authService.login({ email: '...', password: '...' }).subscribe(res => {
  //   if (res.success) {
  //     console.log('Connecté !', res.user);
  //   }
  // });

  // si on veut par exemple vérifier que la personne est connecté :

  // <div *if="authService.isLoggedIn$ | async">
  // Bienvenue !
  // </div>

  // Inscription
  register(userData: any): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(this.apiUrl, userData, {
      withCredentials: true
    });
  }

  // Réinitialisation du mot de passe
  resetPassword(resetData: { email: string; newPassword: string }): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(this.apiUrl, {
      action: 'reset-password',
      email: resetData.email,
      password: resetData.newPassword
    }, {
      withCredentials: true
    });
  }

  // Déconnexion
  logout(): void {
    this.http.post<AuthResponse>(this.apiUrl, { action: 'logout' }, {
      withCredentials: true
    }).subscribe({ // subscribe sert a recevoir les données
      next: () => {
        this.isLoggedInSubject.next(false);
        this.currentUserSubject.next(null); // On vide l'utilisateur à la déconnexion
        this.panierService.viderPanier();
      },
      error: (err) => {
        console.error('Erreur lors de la déconnexion:', err);
        this.isLoggedInSubject.next(false);
        this.currentUserSubject.next(null); // On vide aussi en cas d'erreur si on force la déco locale
        this.panierService.viderPanier();
      }
    });
  }

  // Sessions
  checkSession(): Observable<boolean> {
    return this.http.get<SessionStatus>(`${this.apiUrl}?action=check-session`, {
      withCredentials: true
    }).pipe(
      map((response) => {
        const isLoggedIn = response.logged_in === true;
        this.isLoggedInSubject.next(isLoggedIn);

        // Si on est connecté et que le serveur renvoie l'utilisateur, on le stocke
        if (isLoggedIn && response.user) {
          this.currentUserSubject.next(response.user);
        }

        return isLoggedIn;
      }),
      catchError((error) => {
        console.error('Erreur lors de la vérification de la session:', error);
        this.isLoggedInSubject.next(false);
        return of(false);
      })
    );
  }

  refreshSession(): void {
    this.checkSession().subscribe();
  }

  getIsLoggedIn(): boolean {
    return this.isLoggedInSubject.value;
  }

  getUser(): User | null {
    return this.currentUserSubject.value; // retourne l'utilisateur actuellement connecté pour pas attendre comme avec un subscribe
  }

  isStudent(): boolean {
    return this.getUser()?.statut_etud || false;
  }
}