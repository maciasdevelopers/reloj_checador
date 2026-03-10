import { Component, OnInit } from '@angular/core';
import { AttendanceService } from '../../services/attendance.service';
import { AuthService } from '../../services/auth.service';
import { Router } from '@angular/router';
import { ToastController, LoadingController } from '@ionic/angular';

@Component({
  selector: 'app-home',
  templateUrl: './home.page.html',
  styleUrls: ['./home.page.scss'],
  standalone: false,
})
export class HomePage implements OnInit {
  status: any;

  constructor(
    private attendanceService: AttendanceService,
    private authService: AuthService,
    private router: Router,
    private toastCtrl: ToastController,
    private loadingCtrl: LoadingController
  ) {}

  ngOnInit() {
    this.loadStatus();
  }

  loadStatus() {
    this.attendanceService.getStatus().subscribe({
      next: (res) => this.status = res,
      error: () => this.router.navigateByUrl('/login')
    });
  }

  async handleAction(type: 'checkIn' | 'checkOut') {
    const loading = await this.loadingCtrl.create({
      message: type === 'checkIn' ? 'Registrando entrada...' : 'Registrando salida...'
    });
    await loading.present();

    try {
      // 1. Validar conexión a internet
      await this.attendanceService.checkInternetConnection();

      // 2. Obtener ubicación actual
      const pos = await this.attendanceService.getCurrentPosition();
      const lat = pos.coords.latitude;
      const lon = pos.coords.longitude;

      // 3. Validar distancia localmente (si tenemos las coordenadas de la empresa)
      if (this.status && this.status.company_location) {
        const companyLat = parseFloat(this.status.company_location.latitude);
        const companyLon = parseFloat(this.status.company_location.longitude);
        const distance = this.attendanceService.calculateDistance(lat, lon, companyLat, companyLon);

        if (distance > 100) {
          loading.dismiss();
          const toast = await this.toastCtrl.create({
            message: `Estás fuera del rango (100m). Distancia actual: ${Math.round(distance)}m`,
            duration: 4000,
            color: 'danger'
          });
          toast.present();
          return;
        }
      }

      // 4. Enviar al backend
      const action = type === 'checkIn'
        ? this.attendanceService.checkIn(lat, lon)
        : this.attendanceService.checkOut(lat, lon);

      action.subscribe({
        next: async (res: any) => {
          loading.dismiss();
          const toast = await this.toastCtrl.create({
            message: res.message,
            duration: 3000,
            color: 'success'
          });
          toast.present();
          this.loadStatus();
        },
        error: async (err) => {
          loading.dismiss();
          const toast = await this.toastCtrl.create({
            message: err.error?.message || 'Error en el registro',
            duration: 4000,
            color: 'danger'
          });
          toast.present();
        }
      });

    } catch (err: any) {
      loading.dismiss();
      const toast = await this.toastCtrl.create({
        message: err.message || 'Error al obtener ubicación o conexión',
        duration: 4000,
        color: 'danger'
      });
      toast.present();
    }
  }

  async logout() {
    this.authService.logout().subscribe(() => {
      this.router.navigateByUrl('/login');
    });
  }
}
