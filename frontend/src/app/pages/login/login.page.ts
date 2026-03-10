import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { ToastController, LoadingController } from '@ionic/angular';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
  standalone: false,
})
export class LoginPage {
  credentials = { email: '', password: '' };

  constructor(
    private authService: AuthService,
    private router: Router,
    private toastCtrl: ToastController,
    private loadingCtrl: LoadingController
  ) {}

  async login() {
    const loading = await this.loadingCtrl.create({ message: 'Iniciando sesión...' });
    await loading.present();

    this.authService.login(this.credentials).subscribe({
      next: () => {
        loading.dismiss();
        this.router.navigateByUrl('/home');
      },
      error: async (err) => {
        loading.dismiss();
        const toast = await this.toastCtrl.create({
          message: err.error?.message || 'Error al iniciar sesión',
          duration: 2000,
          color: 'danger'
        });
        toast.present();
      }
    });
  }
}
