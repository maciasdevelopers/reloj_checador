import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { environment } from '../../environments/environment';
import { Geolocation } from '@capacitor/geolocation';
import { Network } from '@capacitor/network';
import { from, Observable, throwError } from 'rxjs';
import { switchMap } from 'rxjs/operators';

@Injectable({
  providedIn: 'root'
})
export class AttendanceService {
  private apiUrl = `${environment.apiUrl}`;

  constructor(private http: HttpClient) {}

  private getHeaders() {
    const token = localStorage.getItem('auth_token');
    return new HttpHeaders({
      Authorization: `Bearer ${token}`
    });
  }

  async checkInternetConnection() {
    const status = await Network.getStatus();
    if (!status.connected) {
      throw new Error('Sin conexión a internet. El registro requiere conexión activa.');
    }
    return true;
  }

  async getCurrentPosition() {
    return await Geolocation.getCurrentPosition();
  }

  calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
    const earthRadius = 6371000; // metros
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return earthRadius * c;
  }

  checkIn(lat: number, lon: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/check-in`, {
      latitude: lat,
      longitude: lon
    }, { headers: this.getHeaders() });
  }

  checkOut(lat: number, lon: number): Observable<any> {
    return this.http.post(`${this.apiUrl}/check-out`, {
      latitude: lat,
      longitude: lon
    }, { headers: this.getHeaders() });
  }

  getStatus(): Observable<any> {
    return this.http.get(`${this.apiUrl}/status`, { headers: this.getHeaders() });
  }
}
