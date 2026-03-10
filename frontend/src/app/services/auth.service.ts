import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, tap } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = `${environment.apiUrl}`;
  private authSubject = new BehaviorSubject<any>(null);

  constructor(private http: HttpClient) {
    const token = localStorage.getItem('auth_token');
    if (token) {
      this.authSubject.next({ token });
    }
  }

  login(credentials: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/login`, credentials).pipe(
      tap((res: any) => {
        localStorage.setItem('auth_token', res.access_token);
        this.authSubject.next(res);
      })
    );
  }

  logout() {
    return this.http.post(`${this.apiUrl}/logout`, {}, {
      headers: { Authorization: `Bearer ${localStorage.getItem('auth_token')}` }
    }).pipe(
      tap(() => {
        localStorage.removeItem('auth_token');
        this.authSubject.next(null);
      })
    );
  }

  getToken() {
    return localStorage.getItem('auth_token');
  }

  isLoggedIn() {
    return !!this.getToken();
  }
}
