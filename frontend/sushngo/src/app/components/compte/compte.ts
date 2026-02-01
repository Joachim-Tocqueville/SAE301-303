import { Component, OnInit } from '@angular/core';
import { Footer } from '../footer/footer';
import { Navbar } from '../navbar/navbar';
import { RouterModule } from '@angular/router';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { AuthService, User } from '../../services/auth.service';

interface Order {
  id_commande: number;
  date_commande: string;
  prix_total: number;
  mode: string;
  produits: string;
}

@Component({
  selector: 'app-compte',
  imports: [Navbar, Footer, RouterModule, CommonModule, FormsModule],
  templateUrl: './compte.html',
  styleUrl: './compte.css',
})
export class Compte implements OnInit {
  user: User | null = null;
  isEditing: boolean = false;
  orders: Order[] = [];
  message: string = '';
  messageType: 'success' | 'error' = 'success';

  showMdpActuel: boolean = false;
  showMdpNouveau: boolean = false;
  showMdpConfirm: boolean = false;

  // Données du formulaire
  formData = {
    nom: '',
    prenom: '',
    email: '',
    adresse: '',
    telephone: '',
    mdpactuel: '',
    mdpnouveau: '',
    mdpnouveauconfirm: ''
  };

  // Données originales pour comparer les changements
  originalData = {
    nom: '',
    prenom: '',
    email: '',
    adresse: '',
    telephone: ''
  };

  private apiUrl = 'https://sae301-303.onrender.com/api/api.php';

  constructor(
    private authService: AuthService,
    private http: HttpClient
  ) { }

  ngOnInit() {
    this.loadUserData();
    this.loadOrders();
  }

  loadUserData() {
    this.authService.checkSession().subscribe((isLoggedIn) => {
      if (isLoggedIn) {
        this.user = this.authService.getUser();
        if (this.user) {
          this.formData.nom = this.user.nom || '';
          this.formData.prenom = this.user.prenom || '';
          this.formData.email = this.user.email || '';
          this.formData.adresse = this.user.adresse || '';
          this.formData.telephone = this.user.tel || '';

          // Sauvegarder les données originales
          this.originalData = {
            nom: this.formData.nom,
            prenom: this.formData.prenom,
            email: this.formData.email,
            adresse: this.formData.adresse,
            telephone: this.formData.telephone
          };
        }
      }
    });
  }

  loadOrders() {
    this.http.get<Order[]>(`${this.apiUrl}?action=get-orders`, {
      withCredentials: true
    }).subscribe({
      next: (orders) => {
        this.orders = orders.slice(0, 5); // maxime derènes : vu que on a pas mis de pagination, on va juste afficher les 5 dernières les plus récentes
      },
      error: (err) => {
        console.error('Erreur lors du chargement des commandes:', err);
      }
    });
  }

  get fidelite(): number {
    const fideliteValue = this.user?.fidelite ?? 0;
    return typeof fideliteValue === 'number' ? fideliteValue : 0;
  }

  get fideliteProgress(): number {
    // La barre de progression reste limitée à 100 pour l'affichage visuel
    // mais le montant réel s'affiche sans limite
    return Math.min(this.fidelite, 100);
  }

  isStepReached(step: number): boolean {
    return this.fidelite >= step;
  }

  getStepColor(step: number): string {
    return this.isStepReached(step) ? '#FFEA47' : '#2E3F2D';
  }

  getStepStrokeColor(step: number): string {
    return this.isStepReached(step) ? 'black' : 'white';
  }

  getStepTextColor(step: number): string {
    return this.isStepReached(step) ? 'black' : 'white';
  }

  getCurrentReduction(): number {
    const fid = this.fidelite;
    if (fid >= 100) return 20;
    if (fid >= 75) return 15;
    if (fid >= 50) return 10;
    if (fid >= 25) return 5;
    return 0;
  }

  hasReachedAnyStep(): boolean {
    return this.fidelite >= 25;
  }

  toggleEdit() {
    this.isEditing = !this.isEditing;
    if (!this.isEditing) {
      // Réinitialiser les données si on annule
      this.loadUserData();
      this.clearMessage();
      // Réinitialiser les champs de mot de passe
      this.formData.mdpactuel = '';
      this.formData.mdpnouveau = '';
      this.formData.mdpnouveauconfirm = '';
    }
  }

  clearMessage() {
    this.message = '';
  }

  hasChanges(): boolean {
    return (
      this.formData.nom !== this.originalData.nom ||
      this.formData.prenom !== this.originalData.prenom ||
      this.formData.email !== this.originalData.email ||
      this.formData.adresse !== this.originalData.adresse ||
      this.formData.telephone !== this.originalData.telephone ||
      !!(this.formData.mdpnouveau && this.formData.mdpnouveau.trim() !== '')
    );
  }

  validateForm(): { valid: boolean; message: string } {
    // Vérifier que les champs obligatoires sont remplis
    if (!this.formData.nom || !this.formData.prenom || !this.formData.email) {
      return { valid: false, message: 'Les champs nom, prénom et email sont obligatoires' };
    }

    // Vérifier le format de l'email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(this.formData.email)) {
      return { valid: false, message: 'Format d\'email invalide' };
    }

    // Si un nouveau mot de passe est fourni, vérifier les validations
    if (this.formData.mdpnouveau || this.formData.mdpnouveauconfirm) {
      if (!this.formData.mdpactuel) {
        return { valid: false, message: 'Le mot de passe actuel est requis pour changer le mot de passe' };
      }
      if (this.formData.mdpnouveau !== this.formData.mdpnouveauconfirm) {
        return { valid: false, message: 'Les nouveaux mots de passe ne correspondent pas' };
      }
      if (this.formData.mdpnouveau.length < 6) {
        return { valid: false, message: 'Le nouveau mot de passe doit contenir au moins 6 caractères' };
      }
    }

    return { valid: true, message: '' };
  }

  saveChanges() {
    // Vérifier s'il y a des changements
    if (!this.hasChanges()) {
      this.message = 'Aucune modification détectée';
      this.messageType = 'error';
      return;
    }

    // Valider le formulaire
    const validation = this.validateForm();
    if (!validation.valid) {
      this.message = validation.message;
      this.messageType = 'error';
      return;
    }

    // Préparer les données à envoyer
    const updateData: any = {
      action: 'update-user',
      nom: this.formData.nom,
      prenom: this.formData.prenom,
      email: this.formData.email,
      adresse: this.formData.adresse,
      telephone: this.formData.telephone
    };

    // Ajouter le mot de passe si fourni
    if (this.formData.mdpnouveau && this.formData.mdpnouveau.trim() !== '') {
      updateData.current_password = this.formData.mdpactuel;
      updateData.new_password = this.formData.mdpnouveau;
    }

    // Envoyer les données à l'API
    this.http.post<{ success: boolean; message: string }>(this.apiUrl, updateData, {
      withCredentials: true
    }).subscribe({
      next: (response) => {
        if (response.success) {
          this.message = response.message || 'Informations mises à jour avec succès';
          this.messageType = 'success';
          this.isEditing = false;

          // Recharger les données utilisateur
          this.authService.refreshSession();
          setTimeout(() => {
            this.loadUserData();
            // Réinitialiser les champs de mot de passe
            this.formData.mdpactuel = '';
            this.formData.mdpnouveau = '';
            this.formData.mdpnouveauconfirm = '';
          }, 500);
        } else {
          this.message = response.message || 'Erreur lors de la mise à jour';
          this.messageType = 'error';
        }
      },
      error: (err) => {
        console.error('Erreur lors de la sauvegarde:', err);
        this.message = err.error?.message || 'Erreur lors de la sauvegarde des modifications';
        this.messageType = 'error';
      }
    });
  }

  getOrderProducts(order: Order): string[] {
    if (!order.produits || order.produits.trim() === '') {
      return ['Aucun produit'];
    }
    return order.produits.split(', ').filter(p => p.trim() !== '');
  }


}
